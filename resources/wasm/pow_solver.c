#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <stdint.h>
#include <emscripten.h>

/* A self-contained, public-domain SHA256 implementation */
// (For brevity, I'm showing just the function signatures here. The full code block below will contain the full SHA256 implementation)
typedef struct {
    uint8_t  buf[64];
    uint32_t hash[8];
    uint32_t bits[2];
    uint32_t len;
} SHA256_CTX;

void sha256_init(SHA256_CTX *ctx);
void sha256_update(SHA256_CTX *ctx, const uint8_t *data, size_t len);
void sha256_final(SHA256_CTX *ctx, uint8_t *hash);


EMSCRIPTEN_KEEPALIVE
char* solve(const char* input_text, int difficulty) {
    if (difficulty < 1 || difficulty > 6) {
        difficulty = 4;
    }

    char prefix[difficulty + 1];
    for (int i = 0; i < difficulty; i++) {
        prefix[i] = '0';
    }
    prefix[difficulty] = '\0';

    uint32_t nonce = 0;
    char buffer[1024];
    uint8_t hash_result[32];
    char hex_hash[65];

    while (1) {
        sprintf(buffer, "%s:%u", input_text, nonce);

        SHA256_CTX ctx;
        sha256_init(&ctx);
        sha256_update(&ctx, (uint8_t*)buffer, strlen(buffer));
        sha256_final(&ctx, hash_result);

        for (int i = 0; i < 32; i++) {
            sprintf(hex_hash + (i * 2), "%02x", hash_result[i]);
        }
        hex_hash[64] = '\0';

        if (strncmp(hex_hash, prefix, difficulty) == 0) {
            break;
        }

        nonce++;
        if (nonce > 10000000) {
            return NULL;
        }
    }

    char* result = (char*)malloc(strlen(hex_hash) + 20);
    sprintf(result, "%u:%s", nonce, hex_hash);
    return result;
}


#define F(x,y,z) ((x&y)|(~x&z))
#define G(x,y,z) ((x&z)|(y&~z))
#define H(x,y,z) (x^y^z)
#define I(x,y,z) (y^(x|~z))
#define R(x,n)   ((x>>n)|(x<<(32-n)))
#define S(x,n)   (x>>n)

static const uint32_t K[64] = {
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
    0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
    0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
    0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
};

void sha256_transform(SHA256_CTX *ctx, const uint8_t *data) {
    uint32_t a, b, c, d, e, f, g, h, i, j, t1, t2, m[64];

    for (i=0, j=0; i < 16; ++i, j += 4)
        m[i] = (data[j] << 24) | (data[j+1] << 16) | (data[j+2] << 8) | (data[j+3]);
    for ( ; i < 64; ++i)
        m[i] = R(m[i-2], 17) + m[i-15] + R(m[i-16], 7) + m[i-7];

    a = ctx->hash[0]; b = ctx->hash[1]; c = ctx->hash[2]; d = ctx->hash[3];
    e = ctx->hash[4]; f = ctx->hash[5]; g = ctx->hash[6]; h = ctx->hash[7];

    for (i = 0; i < 64; ++i) {
        t1 = h + R(e, 6) + G(e,f,g) + K[i] + m[i];
        t2 = R(a, 2) + F(a,b,c);
        h = g; g = f; f = e; e = d + t1;
        d = c; c = b; b = a; a = t1 + t2;
    }

    ctx->hash[0] += a; ctx->hash[1] += b; ctx->hash[2] += c; ctx->hash[3] += d;
    ctx->hash[4] += e; ctx->hash[5] += f; ctx->hash[6] += g; ctx->hash[7] += h;
}

void sha256_init(SHA256_CTX *ctx) {
    ctx->hash[0] = 0x6a09e667; ctx->hash[1] = 0xbb67ae85; ctx->hash[2] = 0x3c6ef372; ctx->hash[3] = 0xa54ff53a;
    ctx->hash[4] = 0x510e527f; ctx->hash[5] = 0x9b05688c; ctx->hash[6] = 0x1f83d9ab; ctx->hash[7] = 0x5be0cd19;
    ctx->len = 0; ctx->bits[0] = ctx->bits[1] = 0;
}

void sha256_update(SHA256_CTX *ctx, const uint8_t *data, size_t len) {
    uint32_t i = ctx->len;
    ctx->len = (i + len) & 63;
    if ((ctx->bits[0] += len << 3) < (len << 3)) ctx->bits[1]++;
    ctx->bits[1] += len >> 29;
    if (i && i + len >= 64) {
        memcpy(&ctx->buf[i], data, 64 - i);
        sha256_transform(ctx, ctx->buf);
        data += 64 - i; len -= 64 - i;
        i = 0;
    }
    while (len >= 64) {
        sha256_transform(ctx, data);
        data += 64; len -= 64;
    }
    memcpy(&ctx->buf[i], data, len);
}

void sha256_final(SHA256_CTX *ctx, uint8_t *hash) {
    uint32_t i = ctx->len;
    ctx->buf[i++] = 0x80;
    if (i > 56) {
        memset(&ctx->buf[i], 0, 64 - i);
        sha256_transform(ctx, ctx->buf);
        i=0;
    }
    memset(&ctx->buf[i], 0, 56 - i);
    ctx->buf[56] = ctx->bits[1] >> 24; ctx->buf[57] = ctx->bits[1] >> 16;
    ctx->buf[58] = ctx->bits[1] >> 8;  ctx->buf[59] = ctx->bits[1];
    ctx->buf[60] = ctx->bits[0] >> 24; ctx->buf[61] = ctx->bits[0] >> 16;
    ctx->buf[62] = ctx->bits[0] >> 8;  ctx->buf[63] = ctx->bits[0];
    sha256_transform(ctx, ctx->buf);
    for (i=0; i < 8; ++i) {
        hash[i*4+0] = ctx->hash[i] >> 24; hash[i*4+1] = ctx->hash[i] >> 16;
        hash[i*4+2] = ctx->hash[i] >> 8;  hash[i*4+3] = ctx->hash[i];
    }
}
