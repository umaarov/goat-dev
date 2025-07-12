CXX = g++
CXXFLAGS = -std=c++17 -O3 -pthread -Wall
LDFLAGS =

SRCS = src/BM25Index.cpp src/VectorIndex.cpp src/HybridSearcher.cpp src/main.cpp
OBJS = $(SRCS:.cpp=.o)
TARGET = build/engine

.PHONY: all clean

all: $(TARGET)

$(TARGET): $(OBJS)
	@mkdir -p build
	$(CXX) $(CXXFLAGS) -o $(TARGET) $(OBJS) $(LDFLAGS)
	@echo "Build complete. Executable is at $(TARGET)"

%.o: %.cpp
	$(CXX) $(CXXFLAGS) -c $< -o $@

clean:
	rm -f $(OBJS) $(TARGET)
