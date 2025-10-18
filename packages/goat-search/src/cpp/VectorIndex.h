#pragma once
#include "common.h"
#include <unordered_map>
#include <vector>

class VectorIndex {
public:
    std::vector<float> generateEmbedding(const std::vector<std::string>& tokens) const;
    void addVector(int docId, const std::vector<float>& vec);
    std::vector<std::pair<int, double>> search(const std::vector<float>& queryVec, int k) const;
    bool save(const std::string& filepath) const;
    bool load(const std::string& filepath);

private:
    std::unordered_map<int, std::vector<float>> vectors;
};
