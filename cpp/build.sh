#!/bin/bash
echo "Compiling C++ to WASM..."

emcc src/cpp/geometry_optimizer.cpp \
  -o public/assets/wasm/geometry_optimizer.js \
  -O3 \
  -s WASM=1 \
  -s EXPORTED_FUNCTIONS="['_createComplexWeaverGeometry', '_malloc', '_free']" \
  -s EXPORTED_RUNTIME_METHODS="['cwrap']" \
  -s MODULARIZE=1 \
  -s EXPORT_ES6=1

echo "Compilation complete."