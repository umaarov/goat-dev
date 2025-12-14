#define STB_IMAGE_IMPLEMENTATION
#include "stb_image.h"

#define STB_IMAGE_RESIZE_IMPLEMENTATION
#include "stb_image_resize2.h"

#define STB_IMAGE_WRITE_IMPLEMENTATION
#include "stb_image_write.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>
#include <webp/encode.h>

#define MAX_DIMENSION 4096
#define MIN_DIMENSION 1

static const char b64_table[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
char* base64_encode(const unsigned char *data, size_t input_length) {
    if (data == NULL) return NULL;

    size_t output_length = 4 * ((input_length + 2) / 3);
    char *encoded_data = malloc(output_length + 1);
    if (encoded_data == NULL) return NULL;

    for (size_t i = 0, j = 0; i < input_length;) {
        uint32_t octet_a = i < input_length ? data[i++] : 0;
        uint32_t octet_b = i < input_length ? data[i++] : 0;
        uint32_t octet_c = i < input_length ? data[i++] : 0;
        uint32_t triple = (octet_a << 0x10) + (octet_b << 0x08) + octet_c;
        encoded_data[j++] = b64_table[(triple >> 3 * 6) & 0x3F];
        encoded_data[j++] = b64_table[(triple >> 2 * 6) & 0x3F];
        encoded_data[j++] = b64_table[(triple >> 1 * 6) & 0x3F];
        encoded_data[j++] = b64_table[(triple >> 0 * 6) & 0x3F];
    }
    for (int i = 0; i < (3 - (input_length % 3)) % 3; i++) {
        encoded_data[output_length - 1 - i] = '=';
    }
    encoded_data[output_length] = '\0';
    return encoded_data;
}

typedef struct {
    unsigned char *data;
    size_t size;
    size_t capacity;
} mem_buffer;

void write_to_mem(void *context, void *data, int size) {
    mem_buffer *buf = (mem_buffer*)context;
    if (buf->size + size > buf->capacity) {
        size_t new_capacity = (buf->size + size) * 2;
        unsigned char *new_data = realloc(buf->data, new_capacity);
        if (!new_data) return;
        buf->data = new_data;
        buf->capacity = new_capacity;
    }
    memcpy(buf->data + buf->size, data, size);
    buf->size += size;
}

int main(int argc, char **argv) {
    if (argc != 8) {
        fprintf(stderr, "Usage: %s <input> <output_webp> <width> <height> <quality> <lqip_width> <lqip_quality>\n", argv[0]);
        return 1;
    }

    int width, height, channels;
    FILE *f = fopen(argv[1], "rb");
    if (!f) {
        fprintf(stderr, "Error: Input file does not exist or not readable.\n");
        return 1;
    }
    fclose(f);

    unsigned char *img_data = stbi_load(argv[1], &width, &height, &channels, 0);
    if (img_data == NULL) {
        fprintf(stderr, "Failed to load image: %s\n", stbi_failure_reason());
        return 1;
    }

    if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
         fprintf(stderr, "Error: Image too large (%dx%d). Max allowed is %d\n", width, height, MAX_DIMENSION);
         stbi_image_free(img_data);
         return 1;
    }

    int target_width = atoi(argv[3]);
    int target_height = atoi(argv[4]);

    if (target_width < MIN_DIMENSION) target_width = MIN_DIMENSION;
    if (target_height < MIN_DIMENSION) target_height = MIN_DIMENSION;
    if (target_width > MAX_DIMENSION) target_width = MAX_DIMENSION;
    if (target_height > MAX_DIMENSION) target_height = MAX_DIMENSION;

    float aspect_ratio = (float)width / (float)height;

    if (width > target_width || height > target_height) {
        if (width > height) {
            target_height = (int)(target_width / aspect_ratio);
        } else {
            target_width = (int)(target_height * aspect_ratio);
        }
    } else {
        target_width = width;
        target_height = height;
    }

    size_t required_memory = (size_t)target_width * (size_t)target_height * (size_t)channels;
    if (required_memory > 1024 * 1024 * 500) {
         fprintf(stderr, "Error: Resize operation requires too much memory.\n");
         stbi_image_free(img_data);
         return 1;
    }

    unsigned char *resized_data = (unsigned char *)malloc(required_memory);
    if (resized_data == NULL) {
        fprintf(stderr, "Failed to allocate memory for resize.\n");
        stbi_image_free(img_data);
        return 1;
    }

    stbir_resize_uint8_srgb(img_data, width, height, 0, resized_data, target_width, target_height, 0, (stbir_pixel_layout)channels);

    float quality = atof(argv[5]);
    if (quality < 0) quality = 0;
    if (quality > 100) quality = 100;

    WebPConfig config;
    if (!WebPConfigInit(&config) || !WebPConfigPreset(&config, WEBP_PRESET_DEFAULT, quality)) {
        free(resized_data);
        stbi_image_free(img_data);
        return 1;
    }
    config.quality = quality;

    WebPPicture pic;
    if (!WebPPictureInit(&pic)) {
        free(resized_data);
        stbi_image_free(img_data);
        return 1;
    }
    pic.width = target_width;
    pic.height = target_height;
    pic.use_argb = 1;

    if (channels == 4) {
        WebPPictureImportRGBA(&pic, resized_data, target_width * 4);
    } else {
        WebPPictureImportRGB(&pic, resized_data, target_width * 3);
    }

    WebPMemoryWriter writer;
    WebPMemoryWriterInit(&writer);
    pic.writer = WebPMemoryWrite;
    pic.custom_ptr = &writer;

    if (!WebPEncode(&config, &pic)) {
        fprintf(stderr, "WebP encoding failed.\n");
        WebPPictureFree(&pic);
        WebPMemoryWriterClear(&writer);
        free(resized_data);
        stbi_image_free(img_data);
        return 1;
    }
    WebPPictureFree(&pic);

    FILE *out_file = fopen(argv[2], "wb");
    if (!out_file) {
        fprintf(stderr, "Cannot open output file.\n");
        WebPMemoryWriterClear(&writer);
        free(resized_data);
        stbi_image_free(img_data);
        return 1;
    }
    fwrite(writer.mem, 1, writer.size, out_file);
    fclose(out_file);
    WebPMemoryWriterClear(&writer);

    int lqip_width = atoi(argv[6]);
    int lqip_quality = atoi(argv[7]);
    if (lqip_width < 1) lqip_width = 1;
    if (lqip_width > 100) lqip_width = 100;

    int lqip_height = (int)(lqip_width / aspect_ratio);
    if (lqip_height < 1) lqip_height = 1;

    unsigned char *lqip_data = (unsigned char *)malloc(lqip_width * lqip_height * channels);
    if (lqip_data) {
        stbir_resize_uint8_srgb(resized_data, target_width, target_height, 0, lqip_data, lqip_width, lqip_height, 0, (stbir_pixel_layout)channels);

        mem_buffer lqip_buf = { .data = NULL, .size = 0, .capacity = 0 };
        stbi_write_jpg_to_func(write_to_mem, &lqip_buf, lqip_width, lqip_height, channels, lqip_data, lqip_quality);

        char *base64_lqip = base64_encode(lqip_buf.data, lqip_buf.size);
        if(base64_lqip != NULL) {
            printf("%s", base64_lqip);
            free(base64_lqip);
        }
        free(lqip_data);
        free(lqip_buf.data);
    }

    stbi_image_free(img_data);
    free(resized_data);
    return 0;
}
