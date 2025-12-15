#define STB_IMAGE_IMPLEMENTATION
#include "stb_image.h"

#define STB_IMAGE_RESIZE_IMPLEMENTATION
#include "stb_image_resize2.h"

#define STB_IMAGE_WRITE_IMPLEMENTATION
#include "stb_image_write.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <webp/encode.h>

// Simple Base64 encoder for the LQIP
static const char b64_table[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
char* base64_encode(const unsigned char *data, size_t input_length) {
    size_t output_length = 4 * ((input_length + 2) / 3);
    char *encoded_data = malloc(output_length + 1);
    if (encoded_data == NULL) return NULL;

    for (int i = 0, j = 0; i < input_length;) {
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

// Custom memory writer for stb_image_write
typedef struct {
    unsigned char *data;
    size_t size;
    size_t capacity;
} mem_buffer;

void write_to_mem(void *context, void *data, int size) {
    mem_buffer *buf = (mem_buffer*)context;
    if (buf->size + size > buf->capacity) {
        buf->capacity = (buf->size + size) * 2;
        buf->data = realloc(buf->data, buf->capacity);
    }
    memcpy(buf->data + buf->size, data, size);
    buf->size += size;
}


int main(int argc, char **argv) {
    if (argc != 8) {
        fprintf(stderr, "Usage: %s <input> <output_webp> <width> <height> <quality> <lqip_width> <lqip_quality>\n", argv[0]);
        return 1;
    }

    // --- 1. Load Input Image ---
    int width, height, channels;
    unsigned char *img_data = stbi_load(argv[1], &width, &height, &channels, 0);
    if (img_data == NULL) {
        fprintf(stderr, "Failed to load image %s: %s\n", argv[1], stbi_failure_reason());
        return 1;
    }

    // --- 2. Resize for Main WebP Image ---
    int target_width = atoi(argv[3]);
    int target_height = atoi(argv[4]);
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

    unsigned char *resized_data = (unsigned char *)malloc(target_width * target_height * channels);
    if (resized_data == NULL) {
        fprintf(stderr, "Failed to allocate memory for resized image data\n");
        stbi_image_free(img_data);
        return 1;
    }

    stbir_resize_uint8_srgb(img_data, width, height, 0, resized_data, target_width, target_height, 0, (stbir_pixel_layout)channels);

    // --- 3. Encode and Save WebP Image ---
    float quality = atof(argv[5]);

    // Setup the configuration
    WebPConfig config;
    if (!WebPConfigInit(&config) || !WebPConfigPreset(&config, WEBP_PRESET_DEFAULT, quality)) {
        fprintf(stderr, "Failed to init WebP config\n");
        free(resized_data);
        return 1;
    }
    config.quality = quality;

    // Setup the picture view
    WebPPicture pic;
    if (!WebPPictureInit(&pic)) {
        fprintf(stderr, "Failed to init WebP picture\n");
        free(resized_data);
        return 1;
    }
    pic.width = target_width;
    pic.height = target_height;
    pic.use_argb = 1;

    // Import the pixel data into the picture struct
    if (channels == 4) {
        WebPPictureImportRGBA(&pic, resized_data, target_width * 4);
    } else {
        WebPPictureImportRGB(&pic, resized_data, target_width * 3);
    }

    // Setup a memory writer
    WebPMemoryWriter writer;
    WebPMemoryWriterInit(&writer);
    pic.writer = WebPMemoryWrite;
    pic.custom_ptr = &writer;

    // Encode the picture
    if (!WebPEncode(&config, &pic)) {
        fprintf(stderr, "WebP encoding failed. Error: %d\n", pic.error_code);
        WebPPictureFree(&pic);
        WebPMemoryWriterClear(&writer);
        return 1;
    }
    WebPPictureFree(&pic);

    // Write the data from memory to file
    FILE *out_file = fopen(argv[2], "wb");
    if (!out_file) {
        fprintf(stderr, "Cannot open output file %s\n", argv[2]);
        WebPMemoryWriterClear(&writer);
        free(resized_data);
        return 1;
    }
    fwrite(writer.mem, 1, writer.size, out_file);
    fclose(out_file);
    WebPMemoryWriterClear(&writer);

    // --- 4. Create LQIP ---
    int lqip_width = atoi(argv[6]);
    int lqip_quality = atoi(argv[7]);
    int lqip_height = (int)(lqip_width / ((float)target_width / (float)target_height));
    if (lqip_height == 0) lqip_height = 1;

    unsigned char *lqip_data = (unsigned char *)malloc(lqip_width * lqip_height * channels);
    if (lqip_data == NULL) {
        fprintf(stderr, "Failed to allocate memory for LQIP data\n");
        free(resized_data);
        stbi_image_free(img_data);
        return 1;
    }

    stbir_resize_uint8_srgb(resized_data, target_width, target_height, 0, lqip_data, lqip_width, lqip_height, 0, (stbir_pixel_layout)channels);

    mem_buffer lqip_buf = { .data = NULL, .size = 0, .capacity = 0 };
    stbi_write_jpg_to_func(write_to_mem, &lqip_buf, lqip_width, lqip_height, channels, lqip_data, lqip_quality);

    // --- 5. Base64 Encode LQIP and Print to stdout ---
    char *base64_lqip = base64_encode(lqip_buf.data, lqip_buf.size);
    if(base64_lqip != NULL) {
        printf("%s", base64_lqip);
        free(base64_lqip);
    }

    // --- 6. Cleanup ---
    stbi_image_free(img_data);
    free(resized_data);
    free(lqip_data);
    free(lqip_buf.data);

    return 0;
}
