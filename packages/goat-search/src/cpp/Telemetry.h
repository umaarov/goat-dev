#pragma once
#include <string>
#include <vector>
#include <deque>
#include <mutex>
#include <chrono>
#include <tuple>

struct QueryStat {
    std::string query;
    int resultCount;
    double durationMs;
    std::string timestamp;
    std::vector<std::pair<int, double>> topResults;
};

class Telemetry {
public:
    static Telemetry& instance() {
        static Telemetry instance;
        return instance;
    }

    void recordQuery(
        const std::string& query,
        const std::vector<std::string>& tokens,
        const std::vector<std::string>& ngrams,
        const std::vector<std::tuple<int, double, std::string>>& results,
        double ms
    );

    void updateSystemStats(size_t docs, size_t vecs);

private:
    Telemetry() : totalQueries(0), docsIndexed(0), vectorNodes(0) {}

    void writeDashboardData();
    std::string getCurrentTime();

    std::mutex dataMutex;
    std::deque<double> latencyHistory;
    long long totalQueries;

    size_t docsIndexed;
    size_t vectorNodes;
};
