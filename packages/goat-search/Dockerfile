FROM alpine:3.19 AS builder
RUN apk add --no-cache g++ make musl-dev

WORKDIR /source
COPY src/cpp/ .
RUN make

FROM alpine:3.19
WORKDIR /app
COPY --from=builder /source/build/engine /app/goat-engine

EXPOSE 9999

CMD ["/app/goat-engine"]
