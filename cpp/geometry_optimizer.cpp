#include <emscripten.h>
#include <vector>
#define _USE_MATH_DEFINES
#include <cmath>
#include <algorithm>

extern "C" {

EMSCRIPTEN_KEEPALIVE
float* createComplexWeaverGeometry(int detail, float radius, float tube, int p, int q, int* size) {
    const int segments = detail * 120;
    std::vector<float> vertices;
    vertices.reserve(segments * 3 + 3);

    for (int i = 0; i <= segments; ++i) {
        float u = static_cast<float>(i) / segments * M_PI * 2.0f * p;
        float cos_u = cos(u);
        float sin_u = sin(u);
        float cos_qu = cos(q * u);

        float x = (radius + tube * cos_qu) * cos_u;
        float y = (radius + tube * cos_qu) * sin_u;
        float z = tube * sin(q * u);

        vertices.push_back(x);
        vertices.push_back(y);
        vertices.push_back(z);
    }

    *size = vertices.size();
    float* result = (float*)malloc(*size * sizeof(float));
    std::copy(vertices.begin(), vertices.end(), result);
    return result;
}

int main() {
    return 0;
}

}