#include "HybridSearcher.h"
#include "Logger.h"
#include "Telemetry.h"
#include <map>
#include <sstream>
#include <iomanip>
#include <algorithm>
#include <set>
#include <fstream>

std::set<std::string> debug_get_ngrams(const std::string& text, int n = 3) {
    std::set<std::string> ngrams;
    if (text.length() < (size_t)n) return ngrams;
    for (size_t i = 0; i <= text.length() - n; ++i) {
        ngrams.insert(text.substr(i, n));
    }
    return ngrams;
}

HybridSearcher::HybridSearcher() {}

void HybridSearcher::addDocument(const InputDocument& doc) {
    ProcessedDocument p_doc;
    p_doc.id = doc.id;
    tokenize(doc.text, p_doc.tokens);
    p_doc.length = p_doc.tokens.size();

    documentCache[doc.id] = doc.text;

    Logger::log(DEBUG, "Indexing Doc " + std::to_string(doc.id));

    bm25Index.addDocument(p_doc);
    std::vector<float> vec = vectorIndex.generateEmbedding(p_doc.tokens);
    vectorIndex.addVector(doc.id, vec);
    Telemetry::instance().updateSystemStats(doc.id, doc.id);
}

std::string HybridSearcher::getDocumentText(int id) {
    if (documentCache.find(id) != documentCache.end()) {
        return documentCache[id];
    }
    return "[Text not found in cache]";
}

std::vector<int> HybridSearcher::search(const std::string& query, int topK) {
    auto start = std::chrono::high_resolution_clock::now();

    std::vector<std::string> tokens;
    tokenize(query, tokens);
    std::vector<std::string> breakdown_ngrams;
    for(const auto& t : tokens) {
        auto grams = debug_get_ngrams(t, 3);
        breakdown_ngrams.insert(breakdown_ngrams.end(), grams.begin(), grams.end());
    }

    auto bm25_results = bm25Index.search(tokens);
    std::vector<float> query_vec = vectorIndex.generateEmbedding(tokens);
    auto vec_results = vectorIndex.search(query_vec, topK);

    std::map<int, double> final_scores;
    double bm25Weight = bm25_results.empty() ? 0.0 : 0.7;
    double vectorWeight = bm25_results.empty() ? 1.0 : 0.3;

    for(const auto& res : bm25_results) final_scores[res.first] += res.second * bm25Weight;
    for(const auto& res : vec_results) final_scores[res.first] += res.second * vectorWeight;

    std::vector<std::pair<int, double>> sorted_final(final_scores.begin(), final_scores.end());
    std::sort(sorted_final.begin(), sorted_final.end(), [](const auto& a, const auto& b) {
        return a.second > b.second;
    });

    std::vector<std::tuple<int, double, std::string>> rich_results;
    std::vector<int> final_ids;

    for (int i = 0; i < std::min((int)sorted_final.size(), topK); ++i) {
        int id = sorted_final[i].first;
        double score = sorted_final[i].second;
        final_ids.push_back(id);

        rich_results.push_back({id, score, getDocumentText(id)});
    }

    auto end = std::chrono::high_resolution_clock::now();
    double duration = std::chrono::duration_cast<std::chrono::microseconds>(end - start).count() / 1000.0;

    Telemetry::instance().recordQuery(query, tokens, breakdown_ngrams, rich_results, duration);

    return final_ids;
}

bool HybridSearcher::save(const std::string& bm25Path, const std::string& vecPath) {
    bm25Index.finalize();

    bool indexSaved = bm25Index.save(bm25Path) && vectorIndex.save(vecPath);
    if (!indexSaved) return false;

    std::ofstream docFile("index.docs", std::ios::binary);
    if (!docFile) return false;

    size_t cacheSize = documentCache.size();
    docFile.write(reinterpret_cast<const char*>(&cacheSize), sizeof(cacheSize));

    for (const auto& pair : documentCache) {
        int id = pair.first;
        size_t len = pair.second.size();
        docFile.write(reinterpret_cast<const char*>(&id), sizeof(id));
        docFile.write(reinterpret_cast<const char*>(&len), sizeof(len));
        docFile.write(pair.second.c_str(), len);
    }

    Logger::log(INFO, "Saved " + std::to_string(cacheSize) + " documents to index.docs");
    return true;
}

bool HybridSearcher::load(const std::string& bm25Path, const std::string& vecPath) {
    if (!bm25Index.load(bm25Path) || !vectorIndex.load(vecPath)) {
        return false;
    }

    std::ifstream docFile("index.docs", std::ios::binary);
    if (docFile) {
        size_t cacheSize;
        docFile.read(reinterpret_cast<char*>(&cacheSize), sizeof(cacheSize));

        for (size_t i = 0; i < cacheSize; ++i) {
            int id;
            size_t len;
            docFile.read(reinterpret_cast<char*>(&id), sizeof(id));
            docFile.read(reinterpret_cast<char*>(&len), sizeof(len));

            std::string text(len, ' ');
            docFile.read(&text[0], len);

            documentCache[id] = text;
        }
        Logger::log(INFO, "Loaded " + std::to_string(cacheSize) + " documents from index.docs");
    } else {
        Logger::log(WARN, "Could not load index.docs (Cache empty)");
    }

    return true;
}
