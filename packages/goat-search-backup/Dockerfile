FROM alpine:3.19 AS builder
RUN apk add --no-cache g++ make musl-dev wget
WORKDIR /source
COPY src/cpp/ .
RUN wget -O json.hpp https://github.com/nlohmann/json/releases/download/v3.11.3/json.hpp
RUN make

FROM alpine:3.19
RUN apk add --no-cache libstdc++
WORKDIR /app
COPY --from=builder /source/build/engine /usr/local/bin/goat-engine
EXPOSE 9999
CMD ["/usr/local/bin/goat-engine"]
