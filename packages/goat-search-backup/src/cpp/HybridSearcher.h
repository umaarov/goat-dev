#pragma once
#include "BM25Index.h"
#include "VectorIndex.h"
#include <unordered_map>

class HybridSearcher {
public:
    HybridSearcher();
    void addDocument(const InputDocument& doc);
    std::vector<int> search(const std::string& query, int topK);
    bool save(const std::string& bm25Path, const std::string& vecPath);
    bool load(const std::string& bm25Path, const std::string& vecPath);

    std::string getDocumentText(int id);

private:
    BM25Index bm25Index;
    VectorIndex vectorIndex;
    std::unordered_map<int, std::string> documentCache;
};
