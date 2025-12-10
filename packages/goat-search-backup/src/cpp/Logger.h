#pragma once
#include <iostream>
#include <string>
#include <chrono>
#include <iomanip>
#include <mutex>
#include <sstream>

#define RESET   "\033[0m"
#define GREY    "\033[1;30m"
#define RED     "\033[1;31m"
#define GREEN   "\033[1;32m"
#define YELLOW  "\033[1;33m"
#define BLUE    "\033[1;34m"
#define PURPLE  "\033[1;35m"
#define CYAN    "\033[1;36m"

enum LogLevel { INFO, DEBUG, WARN, ERROR, PERF, BRAIN, NET };

class Logger {
private:
    static std::mutex logMutex;

public:
    static void log(LogLevel level, const std::string& message) {
        std::lock_guard<std::mutex> lock(logMutex);

        auto now = std::chrono::system_clock::now();
        auto in_time_t = std::chrono::system_clock::to_time_t(now);
        auto ms = std::chrono::duration_cast<std::chrono::milliseconds>(now.time_since_epoch()) % 1000;

        std::cout << GREY << "[" << std::put_time(std::localtime(&in_time_t), "%H:%M:%S")
                  << "." << std::setfill('0') << std::setw(3) << ms.count() << "] " << RESET;

        switch(level) {
            case INFO:  std::cout << BLUE   << "[INFO]  " << RESET; break;
            case DEBUG: std::cout << CYAN   << "[DEBUG] " << RESET; break;
            case WARN:  std::cout << YELLOW << "[WARN]  " << RESET; break;
            case ERROR: std::cout << RED    << "[ERROR] " << RESET; break;
            case PERF:  std::cout << PURPLE << "[PERF] " << RESET; break;
            case BRAIN: std::cout << GREEN  << "[BRAIN] " << RESET; break;
            case NET:   std::cout << CYAN   << "[NET] " << RESET; break;
        }
        std::cout << message << RESET << std::endl;
    }
};

inline std::mutex Logger::logMutex;

class ScopedTimer {
    std::string name;
    std::chrono::high_resolution_clock::time_point start;
public:
    ScopedTimer(const std::string& n) : name(n), start(std::chrono::high_resolution_clock::now()) {}
    ~ScopedTimer() {
        auto end = std::chrono::high_resolution_clock::now();
        auto duration = std::chrono::duration_cast<std::chrono::microseconds>(end - start).count();
        std::ostringstream oss;
        oss << name << " took " << (duration / 1000.0) << " ms";
        Logger::log(PERF, oss.str());
    }
};
