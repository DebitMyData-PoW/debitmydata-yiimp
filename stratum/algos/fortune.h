#ifndef FORTUNE_H
#define FORTUNE_H
#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void fortune_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif
#endif
