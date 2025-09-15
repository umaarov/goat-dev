#include "HybridSearcher.h"
#include <map>

HybridSearcher::HybridSearcher() {}

void HybridSearcher::addDocument(const InputDocument& doc) {
    ProcessedDocument p_doc;
    p_doc.id = doc.id;
    tokenize(doc.text, p_doc.tokens);
    p_doc.length = p_doc.tokens.size();

    bm25Index.addDocument(p_doc);
    std::vector<float> vec = vectorIndex.generateEmbedding(p_doc.tokens);
    vectorIndex.addVector(doc.id, vec);
}

std::vector<int> HybridSearcher::search(const std::string& query, int topK) {
    std::vector<std::string> tokens;
    tokenize(query, tokens);

    auto bm25_results = bm25Index.search(tokens);

    std::vector<float> query_vec = vectorIndex.generateEmbedding(tokens);
    auto vec_results = vectorIndex.search(query_vec, topK);

    std::map<int, double> final_scores;
    double bm25Weight = 0.4;
    double vectorWeight = 0.6;

    for(const auto& res : bm25_results) final_scores[res.first] += res.second * bm25Weight;
    for(const auto& res : vec_results) final_scores[res.first] += res.second * vectorWeight;

    std::vector<std::pair<int, double>> sorted_final(final_scores.begin(), final_scores.end());
    std::sort(sorted_final.begin(), sorted_final.end(), [](const auto& a, const auto& b) {
        return a.second > b.second;
    });

    std::vector<int> final_ids;
    for (int i = 0; i < std::min((int)sorted_final.size(), topK); ++i) {
        final_ids.push_back(sorted_final[i].first);
    }
    return final_ids;
}

bool HybridSearcher::save(const std::string& bm25Path, const std::string& vecPath) {
    bm25Index.finalize();
    return bm25Index.save(bm25Path) && vectorIndex.save(vecPath);
}

bool HybridSearcher::load(const std::string& bm25Path, const std::string& vecPath) {
    return bm25Index.load(bm25Path) && vectorIndex.load(vecPath);
}
