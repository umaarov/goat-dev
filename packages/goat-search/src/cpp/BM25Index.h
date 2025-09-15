#pragma once
#include "common.h"
#include <unordered_map>
#include <vector>

class BM25Index {
public:
    BM25Index(double k1 = 1.2, double b = 0.75);
    void addDocument(const ProcessedDocument& doc);
    std::vector<std::pair<int, double>> search(const std::vector<std::string>& tokens) const;
    void finalize();
    bool save(const std::string& filepath) const;
    bool load(const std::string& filepath);

private:
    std::unordered_map<std::string, std::vector<std::pair<int, int>>> index;
    std::unordered_map<int, int> docLengths;
    double k1, b;
    double avgDocLength;
};
