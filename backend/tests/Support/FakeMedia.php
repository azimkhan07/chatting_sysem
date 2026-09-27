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
    public static function png(int $width, int $height, int $extraBytes = 0, string $name = 'photo.png'): UploadedFile
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

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private static function chunk(string $name, string $data): string
    {
        $length = pack('N', strlen($data));
        $checksum = pack('N', crc32($name.$data));

        return $length.$name.$data.$checksum;
    }

    /**
     * Builds an MP4 carrying a real `ftyp` box, which is what finfo needs to
     * report video/mp4. Zero-byte fakes are rejected by the media processors.
     */
    public static function mp4(string $name = 'clip.mp4', int $size = 2048): UploadedFile
    {
        return self::video($name, self::ftypBox('isom', 'isomiso2avc1mp41'), $size);
    }

    public static function mov(string $name = 'clip.mov', int $size = 2048): UploadedFile
    {
        return self::video($name, self::ftypBox('qt  '), $size);
    }

    public static function webm(string $name = 'clip.webm', int $size = 2048): UploadedFile
    {
        return self::video($name, self::ebmlHeader(), $size);
    }

    /**
     * @param  string  $header  real container magic bytes the sniffer must detect
     * @param  int  $size  total file size in bytes
     */
    public static function video(string $name, string $header, int $size): UploadedFile
    {
        $bytes = str_pad($header, max($size, strlen($header)), "\0");

        $path = tempnam(sys_get_temp_dir(), 'amtefakevideo');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'application/octet-stream', null, true);
    }

    private static function ftypBox(string $majorBrand, string $compatibleBrands = ''): string
    {
        $payload = $majorBrand."\x00\x00\x02\x00".$compatibleBrands;
        $length = 8 + strlen($payload);

        return pack('N', $length).'ftyp'.$payload;
    }

    private static function ebmlHeader(): string
    {
        $length = static fn (int $bytes): string => chr(0x80 | $bytes);

        $children = "\x42\x86".$length(1)."\x01"          // EBMLVersion
            ."\x42\xF7".$length(1)."\x01"                 // EBMLReadVersion
            ."\x42\xF2".$length(1)."\x04"                 // EBMLMaxIDLength
            ."\x42\xF3".$length(1)."\x08"                 // EBMLMaxSizeLength
            ."\x42\x82".$length(4).'webm'                 // DocType
            ."\x42\x87".$length(1)."\x02"                 // DocTypeVersion
            ."\x42\x85".$length(1)."\x02";                // DocTypeReadVersion

        return "\x1A\x45\xDF\xA3".$length(strlen($children)).$children;
    }
}
