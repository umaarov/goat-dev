# ---- Stage 1: The Builder ----
FROM gcc:latest AS builder
WORKDIR /app
COPY ./packages/goat-search/src/cpp/ .
RUN curl -L -o json.hpp https://github.com/nlohmann/json/releases/latest/download/json.hpp
RUN make

# ---- Stage 2: The Final Image ----
FROM alpine:latest
WORKDIR /app
COPY --from=builder /app/build/engine .
EXPOSE 9999
CMD ["./engine"]
