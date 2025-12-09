#include "VectorIndex.h"
#include <fstream>
#include <algorithm>
#include <vector>
#include <functional>
#include <numeric>

std::vector<float> VectorIndex::generateEmbedding(const std::vector<std::string>& tokens) const {
    std::vector<float> vec(VECTOR_DIMENSION, 0.0f);
    if (tokens.empty()) return vec;

    std::hash<std::string> hasher;
    for (const auto& token : tokens) {
        size_t h = hasher(token);
        for (int i = 0; i < VECTOR_DIMENSION; ++i) {
            vec[i] += (h % 1000 - 500) / 500.0f * (1.0f / (i + 1));
        }
    }
    double norm = sqrt(std::inner_product(vec.begin(), vec.end(), vec.begin(), 0.0));
    if (norm > 0.0) {
        for (float& val : vec) val /= norm;
    }
    return vec;
}

void VectorIndex::addVector(int docId, const std::vector<float>& vec) {
    vectors[docId] = vec;
}

std::vector<std::pair<int, double>> VectorIndex::search(const std::vector<float>& queryVec, int k) const {
    std::vector<std::pair<int, double>> allScores;
    for (const auto& pair : vectors) {
        allScores.push_back({pair.first, cosine_similarity(queryVec, pair.second)});
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
        ofs.write(reinterpret_cast<const char*>(p.second.data()), VECTOR_DIMENSION * sizeof(float));
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
        std::vector<float> vec(VECTOR_DIMENSION);
        ifs.read(reinterpret_cast<char*>(&docId), sizeof(docId));
        ifs.read(reinterpret_cast<char*>(vec.data()), VECTOR_DIMENSION * sizeof(float));
        vectors[docId] = vec;
    }
    return true;
}
