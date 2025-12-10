#include "HybridSearcher.h"
#include "json.hpp"
#include "Logger.h"
#include <iostream>
#include <string>
#include <thread>
#include <sys/socket.h>
#include <netinet/in.h>
#include <unistd.h>
#include <mutex>

using json = nlohmann::json;

HybridSearcher searcher;
std::mutex searcher_mutex;

void handle_connection(int client_socket) {
    char buffer[8192] = {0};
    ssize_t bytesRead = read(client_socket, buffer, 8192);

    if (bytesRead <= 0) {
        close(client_socket);
        return;
    }

    std::string command_str(buffer);

    std::string log_preview = command_str.length() > 60 ? command_str.substr(0, 60) + "..." : command_str;
    std::replace(log_preview.begin(), log_preview.end(), '\n', ' ');
    Logger::log(NET, "Received Payload (" + std::to_string(bytesRead) + " bytes): " + log_preview);

    std::string response;
    try {
        size_t first_space = command_str.find(' ');
        if (first_space == std::string::npos) throw std::runtime_error("Invalid Protocol Format");

        std::string command = command_str.substr(0, first_space);
        std::string payload = command_str.substr(first_space + 1);

        std::lock_guard<std::mutex> lock(searcher_mutex);

        if (command == "INDEX") {
            ScopedTimer t("Indexing Document");
            auto j = json::parse(payload);
            InputDocument doc = {j["id"], j["text"]};
            searcher.addDocument(doc);
            response = "{\"status\":\"ok\"}";
            Logger::log(INFO, "Indexed Doc ID: " + std::to_string(doc.id));

        } else if (command == "SEARCH") {
            ScopedTimer t("Full Search Request");
            auto j = json::parse(payload);
            std::string query = j["query"];
            Logger::log(INFO, "Processing Query: \"" + query + "\"");

            auto results = searcher.search(query, 50);
            response = json(results).dump();
            Logger::log(INFO, "Returning " + std::to_string(results.size()) + " results.");

        } else if (command == "SAVE") {
            Logger::log(INFO, "Saving Index to disk...");
            searcher.save("index.bm25", "index.vec");
            response = "{\"status\":\"saved\"}";
            Logger::log(INFO, "Index Saved Successfully.");

        } else {
            Logger::log(WARN, "Unknown Command Received: " + command);
            response = "{\"error\":\"unknown command\"}";
        }
    } catch (const std::exception& e) {
        Logger::log(ERROR, "Exception handling request: " + std::string(e.what()));
        response = std::string("{\"error\":\"") + e.what() + "\"}";
    }

    send(client_socket, response.c_str(), response.length(), 0);
    close(client_socket);
}

void start_server(int port) {
    int server_fd;
    struct sockaddr_in address;
    int opt = 1;
    int addrlen = sizeof(address);

    if ((server_fd = socket(AF_INET, SOCK_STREAM, 0)) == 0) {
        perror("socket failed"); exit(EXIT_FAILURE);
    }
    setsockopt(server_fd, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt));
    address.sin_family = AF_INET;
    address.sin_addr.s_addr = INADDR_ANY;
    address.sin_port = htons(port);

    if (bind(server_fd, (struct sockaddr *)&address, sizeof(address)) < 0) {
        perror("bind failed"); exit(EXIT_FAILURE);
    }
    if (listen(server_fd, 10) < 0) {
        perror("listen"); exit(EXIT_FAILURE);
    }

    Logger::log(INFO, "GOAT SEARCH ENGINE STARTED");
    Logger::log(NET, "Daemon listening on port " + std::to_string(port) + "...");

    while (true) {
        int client_socket;
        if ((client_socket = accept(server_fd, (struct sockaddr *)&address, (socklen_t*)&addrlen)) < 0) {
            Logger::log(ERROR, "Socket Accept Failed");
            continue;
        }
        std::thread(handle_connection, client_socket).detach();
    }
}

int main() {
    Logger::log(INFO, "Booting System...");
    if (!searcher.load("index.bm25", "index.vec")) {
        Logger::log(WARN, "No existing index found. Starting Fresh.");
    } else {
        Logger::log(INFO, "Indexes loaded from disk successfully.");
    }
    start_server(9999);
    return 0;
}
