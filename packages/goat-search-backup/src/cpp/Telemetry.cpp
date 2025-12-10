#include "Telemetry.h"
#include "json.hpp"
#include <fstream>
#include <sstream>
#include <iomanip>
#include <ctime>

using json = nlohmann::json;

//Telemetry& Telemetry::instance() {
//    static Telemetry instance;
//    return instance;
//}

std::string Telemetry::getCurrentTime() {
    auto now = std::chrono::system_clock::now();
    auto in_time_t = std::chrono::system_clock::to_time_t(now);
    std::stringstream ss;
    ss << std::put_time(std::localtime(&in_time_t), "%H:%M:%S");
    return ss.str();
}

void Telemetry::recordQuery(
    const std::string& query,
    const std::vector<std::string>& tokens,
    const std::vector<std::string>& ngrams,
    const std::vector<std::tuple<int, double, std::string>>& results,
    double ms
) {
    std::lock_guard<std::mutex> lock(dataMutex);

    json j;
    j["timestamp"] = getCurrentTime();
    j["query"] = query;
    j["latency_ms"] = ms;

    j["debug_tree"]["tokens"] = tokens;
    j["debug_tree"]["ngrams"] = ngrams;

    j["results"] = json::array();

    int count = 0;
    for(const auto& res : results) {
        if(count++ > 50) break;
        std::string fullText = std::get<2>(res);
        std::string snippet = fullText.length() > 100 ? fullText.substr(0, 100) + "..." : fullText;

        j["results"].push_back({
            {"id", std::get<0>(res)},
            {"score", std::get<1>(res)},
            {"snippet", snippet}
        });
    }

    std::ofstream ofs("telemetry_latest.json");
    ofs << j.dump(4);
    ofs.close();
}

void Telemetry::updateSystemStats(size_t docs, size_t vecs) {
    std::lock_guard<std::mutex> lock(dataMutex);
    docsIndexed = docs;
    vectorNodes = vecs;
//    writeDashboardData();
}

void Telemetry::writeDashboardData() {
//    json j;
//
//    j["system"]["status"] = "OPERATIONAL";
//    j["system"]["total_queries"] = totalQueries;
//    j["system"]["docs_indexed"] = docsIndexed;
//    j["system"]["vector_nodes"] = vectorNodes;
//    j["latency"] = latencyHistory;
//    j["queries"] = json::array();
//    for (auto it = recentQueries.rbegin(); it != recentQueries.rend(); ++it) {
//        json q;
//        q["time"] = it->timestamp;
//        q["term"] = it->query;
//        q["count"] = it->resultCount;
//        q["latency"] = it->durationMs;
//
//        q["top_matches"] = json::array();
//        for(auto& res : it->topResults) {
//            q["top_matches"].push_back({
//                {"doc_id", res.first},
//                {"score", res.second}
//            });
//        }
//        j["queries"].push_back(q);
//    }
//
//    std::ofstream ofs("telemetry.json");
//    ofs << j.dump(4);
//    ofs.close();
}
