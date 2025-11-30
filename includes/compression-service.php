<?php

// declare(strict_types=1);

// namespace App\Utils;

// use RuntimeException;

/**
 * Utility class for compressing and decompressing strings using GZIP and Base64.
 *
 * Usage:
 *
 * use App\Utils\CompressionService;
 *
 * $original     = 'Example string for compression.';
 * $compressed   = CompressionService::compress($original);
 * $decompressed = CompressionService::decompress($compressed);
 *
 * echo "Original: $original\n";
 * echo "Compressed: $compressed\n";
 * echo "Decompressed: $decompressed\n";
 */
class CompressionService
{
    const COMPRESSION_LEVEL = 9;
    /**
     * Compresses the given string using GZIP (level 9) and encodes it with Base64.
     *
     * @param  string $input The plain text input string.
     * @return string The Base64-encoded, compressed string.
     */
    public static function compress(string $input): string
    {
        $compressed = gzcompress($input, self::COMPRESSION_LEVEL);

        if ($compressed === false) {
            throw new RuntimeException('Compression failed.');
        }

        return base64_encode($compressed);
    }

    /**
     * Decodes the given Base64 string and decompresses it using GZIP.
     *
     * @param  string $input The Base64-encoded, compressed string.
     * @return string The original uncompressed string.
     */
    public static function decompress(string $input): string
    {
        $decoded = base64_decode($input, true);

        if ($decoded === false) {
            throw new RuntimeException('Base64 decoding failed.');
        }

        $uncompressed = gzuncompress($decoded);

        if ($uncompressed === false) {
            throw new RuntimeException('Value decompression failed.');
        }

        return $uncompressed;
    }
}
