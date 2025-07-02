FROM node:20-alpine

RUN apk add --no-cache python3 git cmake bash

WORKDIR /app

RUN ["/bin/bash", "-c", " \
    git clone https://github.com/emscripten-core/emsdk.git && \
    cd emsdk && \
    ./emsdk install latest && \
    ./emsdk activate latest \
"]

ENV PATH="/app/emsdk:/app/emsdk/upstream/emscripten:$PATH"
