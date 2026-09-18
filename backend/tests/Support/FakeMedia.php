<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

final class FakeMedia
{
    /**
     * Builds a structurally valid PNG without requiring the GD extension.
     * getimagesize() reads the IHDR header, so dimensions resolve correctly.
     *
     * @param  int  $extraBytes  trailing bytes when simulating oversized uploads
     */
    public static function png(int $width, int $height, int $extraBytes = 0): UploadedFile
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0));
        $idat = self::chunk('IDAT', gzcompress("\x00\x00\x00\x00\x00", 0));
        $iend = self::chunk('IEND', '');

        $bytes = $signature.$ihdr.$idat.$iend;

        if ($extraBytes > 0) {
            $bytes .= str_repeat("\0", $extraBytes);
        }

        $path = tempnam(sys_get_temp_dir(), 'amtefakepng');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'photo.png', 'image/png', null, true);
    }

    private static function chunk(string $name, string $data): string
    {
        $length = pack('N', strlen($data));
        $checksum = pack('N', crc32($name.$data));

        return $length.$name.$data.$checksum;
    }
}
