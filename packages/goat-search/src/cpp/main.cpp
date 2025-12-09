#include "HybridSearcher.h"
#include "json.hpp"
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
    read(client_socket, buffer, 8192);
    std::string command_str(buffer);

    if (!command_str.empty()) {
            std::string log_preview = command_str.substr(0, 50);
            std::cout << "[ACCESS] Received: " << log_preview << "..." << std::endl;
        }

    std::string response;
    try {
        size_t first_space = command_str.find(' ');
        std::string command = command_str.substr(0, first_space);
        std::string payload = command_str.substr(first_space + 1);

        std::lock_guard<std::mutex> lock(searcher_mutex);
        if (command == "INDEX") {
            auto j = json::parse(payload);
            InputDocument doc = {j["id"], j["text"]};
            searcher.addDocument(doc);
            response = "{\"status\":\"ok\"}";
        } else if (command == "SEARCH") {
            auto j = json::parse(payload);
            std::string query = j["query"];
            auto results = searcher.search(query, 50);
            response = json(results).dump();
        } else if (command == "SAVE") {
            searcher.save("index.bm25", "index.vec");
            response = "{\"status\":\"saved\"}";
        } else {
            response = "{\"error\":\"unknown command\"}";
        }
    } catch (const std::exception& e) {
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

    std::cout << "Daemon listening on port " << port << "..." << std::endl;
    while (true) {
        int client_socket;
        if ((client_socket = accept(server_fd, (struct sockaddr *)&address, (socklen_t*)&addrlen)) < 0) {
            perror("accept");
            continue;
        }
        std::thread(handle_connection, client_socket).detach();
    }
}

int main() {
    std::cout << "Loading search indexes..." << std::endl;
    if (!searcher.load("index.bm25", "index.vec")) {
        std::cout << "Could not load index files. Starting with empty index." << std::endl;
    } else {
        std::cout << "Indexes loaded successfully." << std::endl;
    }
    start_server(9999);
    return 0;
}
