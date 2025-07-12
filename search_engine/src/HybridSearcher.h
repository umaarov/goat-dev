#pragma once
#include "BM25Index.h"
#include "VectorIndex.h"

class HybridSearcher {
public:
    HybridSearcher();
    void addDocument(const InputDocument& doc);
    std::vector<int> search(const std::string& query, int topK);
    bool save(const std::string& bm25Path, const std::string& vecPath);
    bool load(const std::string& bm25Path, const std::string& vecPath);

private:
    BM25Index bm25Index;
    VectorIndex vectorIndex;
};
