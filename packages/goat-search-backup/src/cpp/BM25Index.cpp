#include "BM25Index.h"
#include <fstream>
#include <map>
#include <algorithm>

BM25Index::BM25Index(double k1, double b) : k1(k1), b(b), avgDocLength(0) {}

void BM25Index::addDocument(const ProcessedDocument& doc) {
    docLengths[doc.id] = doc.length;
    std::unordered_map<std::string, int> termFreqs;
    for (const auto& token : doc.tokens) {
        termFreqs[token]++;
    }
    for (const auto& pair : termFreqs) {
        index[pair.first].push_back({doc.id, pair.second});
    }
}

void BM25Index::finalize() {
    if (docLengths.empty()) return;
    double totalLength = 0;
    for (const auto& pair : docLengths) totalLength += pair.second;
    avgDocLength = totalLength / docLengths.size();
}

std::vector<std::pair<int, double>> BM25Index::search(const std::vector<std::string>& tokens) const {
    std::map<int, double> docScores;
    size_t N = docLengths.size();
    if (N == 0) return {};

    for (const auto& token : tokens) {
        auto it = index.find(token);
        if (it == index.end()) continue;

        const auto& postings = it->second;
        double idf = log((N - postings.size() + 0.5) / (postings.size() + 0.5) + 1.0);

        for (const auto& posting : postings) {
            int docId = posting.first;
            int freq = posting.second;
            double docLen = docLengths.at(docId);
            double score = idf * (freq * (k1 + 1)) / (freq + k1 * (1 - b + b * docLen / avgDocLength));
            docScores[docId] += score;
        }
    }

    std::vector<std::pair<int, double>> sortedScores(docScores.begin(), docScores.end());
    std::sort(sortedScores.begin(), sortedScores.end(), [](const auto& a, const auto& b) {
        return a.second > b.second;
    });

    return sortedScores;
}

bool BM25Index::save(const std::string& filepath) const {
    std::ofstream ofs(filepath, std::ios::binary);
    if (!ofs) return false;

    // Save params
    ofs.write(reinterpret_cast<const char*>(&k1), sizeof(k1));
    ofs.write(reinterpret_cast<const char*>(&b), sizeof(b));
    ofs.write(reinterpret_cast<const char*>(&avgDocLength), sizeof(avgDocLength));

    // Save docLengths
    size_t docLengthsSize = docLengths.size();
    ofs.write(reinterpret_cast<const char*>(&docLengthsSize), sizeof(docLengthsSize));
    for (const auto& p : docLengths) {
        ofs.write(reinterpret_cast<const char*>(&p.first), sizeof(p.first));
        ofs.write(reinterpret_cast<const char*>(&p.second), sizeof(p.second));
    }

    // Save index
    size_t indexSize = index.size();
    ofs.write(reinterpret_cast<const char*>(&indexSize), sizeof(indexSize));
    for (const auto& p : index) {
        size_t keySize = p.first.size();
        ofs.write(reinterpret_cast<const char*>(&keySize), sizeof(keySize));
        ofs.write(p.first.c_str(), keySize);

        size_t postingsSize = p.second.size();
        ofs.write(reinterpret_cast<const char*>(&postingsSize), sizeof(postingsSize));
        ofs.write(reinterpret_cast<const char*>(p.second.data()), postingsSize * sizeof(std::pair<int, int>));
    }
    return true;
}

bool BM25Index::load(const std::string& filepath) {
    std::ifstream ifs(filepath, std::ios::binary);
    if (!ifs) return false;

    // Load params
    ifs.read(reinterpret_cast<char*>(&k1), sizeof(k1));
    ifs.read(reinterpret_cast<char*>(&b), sizeof(b));
    ifs.read(reinterpret_cast<char*>(&avgDocLength), sizeof(avgDocLength));

    // Load docLengths
    size_t docLengthsSize;
    ifs.read(reinterpret_cast<char*>(&docLengthsSize), sizeof(docLengthsSize));
    for (size_t i = 0; i < docLengthsSize; ++i) {
        int key; int val;
        ifs.read(reinterpret_cast<char*>(&key), sizeof(key));
        ifs.read(reinterpret_cast<char*>(&val), sizeof(val));
        docLengths[key] = val;
    }

    // Load index
    size_t indexSize;
    ifs.read(reinterpret_cast<char*>(&indexSize), sizeof(indexSize));
    for (size_t i = 0; i < indexSize; ++i) {
        size_t keySize;
        ifs.read(reinterpret_cast<char*>(&keySize), sizeof(keySize));
        std::string key(keySize, ' ');
        ifs.read(&key[0], keySize);

        size_t postingsSize;
        ifs.read(reinterpret_cast<char*>(&postingsSize), sizeof(postingsSize));
        std::vector<std::pair<int, int>> postings(postingsSize);
        ifs.read(reinterpret_cast<char*>(postings.data()), postingsSize * sizeof(std::pair<int, int>));
        index[key] = postings;
    }
    return true;
}
