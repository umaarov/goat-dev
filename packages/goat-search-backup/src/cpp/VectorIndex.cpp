#include "VectorIndex.h"
#include "Logger.h"
#include <fstream>
#include <algorithm>
#include <vector>
#include <cmath>
#include <map>
#include <set>

std::set<std::string> get_ngrams(const std::string& text, int n = 3) {
    std::set<std::string> ngrams;
    if (text.length() < (size_t)n) return ngrams;
    for (size_t i = 0; i <= text.length() - n; ++i) {
        ngrams.insert(text.substr(i, n));
    }
    return ngrams;
}

std::vector<float> VectorIndex::generateEmbedding(const std::vector<std::string>& tokens) const {
    const int DIM = 1024;
    std::vector<float> vec(DIM, 0.0f);

    for (const auto& token : tokens) {
        std::set<std::string> ngrams = get_ngrams(token, 3);

        for (const auto& gram : ngrams) {
            size_t h = std::hash<std::string>{}(gram);
            int idx = h % DIM;
            vec[idx] += 1.0f;
        }
    }

    double norm = 0.0;
    for (float v : vec) norm += v * v;
    norm = sqrt(norm);

    if (norm > 0) {
        for (float& v : vec) v /= norm;
    }

    return vec;
}

void VectorIndex::addVector(int docId, const std::vector<float>& vec) {
    vectors[docId] = vec;
}

std::vector<std::pair<int, double>> VectorIndex::search(const std::vector<float>& queryVec, int k) const {
    std::vector<std::pair<int, double>> allScores;

    double MIN_SCORE_THRESHOLD = 0.20;

    for (const auto& pair : vectors) {
        double score = cosine_similarity(queryVec, pair.second);

        if (score > MIN_SCORE_THRESHOLD) {
            allScores.push_back({pair.first, score});
        }
    }

    std::sort(allScores.begin(), allScores.end(), [](const auto& a, const auto& b) {
        return a.second > b.second;
    });

    if (allScores.size() > (size_t)k) {
        allScores.resize(k);
    }
    return allScores;
}

bool VectorIndex::save(const std::string& filepath) const {
    std::ofstream ofs(filepath, std::ios::binary);
    if (!ofs) return false;
    size_t totalSize = vectors.size();
    ofs.write(reinterpret_cast<const char*>(&totalSize), sizeof(totalSize));
    for (const auto& p : vectors) {
        ofs.write(reinterpret_cast<const char*>(&p.first), sizeof(p.first));
        size_t dim = p.second.size();
        ofs.write(reinterpret_cast<const char*>(p.second.data()), dim * sizeof(float));
    }
    return true;
}

bool VectorIndex::load(const std::string& filepath) {
    std::ifstream ifs(filepath, std::ios::binary);
    if (!ifs) return false;
    size_t totalSize;
    ifs.read(reinterpret_cast<char*>(&totalSize), sizeof(totalSize));
    for (size_t i = 0; i < totalSize; ++i) {
        int docId;
        std::vector<float> vec(1024);
        ifs.read(reinterpret_cast<char*>(&docId), sizeof(docId));
        ifs.read(reinterpret_cast<char*>(vec.data()), 1024 * sizeof(float));
        vectors[docId] = vec;
    }
    return true;
}
