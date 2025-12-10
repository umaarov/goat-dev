#pragma once
#include <string>
#include <vector>
#include <cctype>
#include <algorithm>
#include <cmath>

const int VECTOR_DIMENSION = 1024;

struct InputDocument {
    int id;
    std::string text;
};

struct ProcessedDocument {
    int id;
    int length;
    std::vector<std::string> tokens;
};

inline void tokenize(const std::string& text, std::vector<std::string>& tokens) {
    std::string current_token;
    for (char ch : text) {
        if (std::isalnum(ch)) {
            current_token += std::tolower(ch);
        } else if (!current_token.empty()) {
            tokens.push_back(current_token);
            current_token.clear();
        }
    }
    if (!current_token.empty()) {
        tokens.push_back(current_token);
    }
}

inline double cosine_similarity(const std::vector<float>& a, const std::vector<float>& b) {
    double dot_product = 0.0, norm_a = 0.0, norm_b = 0.0;
    for (size_t i = 0; i < a.size(); ++i) {
        dot_product += a[i] * b[i];
        norm_a += a[i] * a[i];
        norm_b += b[i] * b[i];
    }
    if (norm_a == 0.0 || norm_b == 0.0) return 0.0;
    return dot_product / (sqrt(norm_a) * sqrt(norm_b));
}
