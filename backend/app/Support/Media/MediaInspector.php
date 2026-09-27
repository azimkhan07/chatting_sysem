<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;

final class MediaInspector
{
    /**
     * Image mime types we accept, mapped to the extension the file is stored as.
     * The keys come from getimagesize(), which reads the file header, so a
     * renamed text file can never pass as an image.
     *
     * @var array<string, string>
     */
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Video mime types detected with finfo. Videos have no cheap header parser,
     * so the magic bytes of the container are what decides.
     *
     * @var array<string, string>
     */
    private const VIDEO_TYPES = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'video/x-m4v' => 'mp4',
    ];

    /**
     * Describes an upload from its real bytes, ignoring the client-supplied name
     * and mime type. Returns null when the content is not a supported image or
     * video, which is what makes renamed payloads ("payload.mp4") fail.
     *
     * @return array{kind: MediaKind, mime: string, extension: string, width: ?int, height: ?int}|null
     */
    public function describe(UploadedFile $file): ?array
    {
        $image = $this->describeImage($file);
        if ($image !== null) {
            return $image;
        }

        return $this->describeVideo($file);
    }

    /**
     * @return array{kind: MediaKind, mime: string, extension: string, width: ?int, height: ?int}|null
     */
    private function describeImage(UploadedFile $file): ?array
    {
        $info = @getimagesize($file->getPathname());

        if ($info === false) {
            return null;
        }

        $mime = strtolower($info['mime']);
        $extension = self::IMAGE_TYPES[$mime] ?? null;

        if ($extension === null) {
            return null;
        }

        return [
            'kind' => MediaKind::Image,
            'mime' => $mime,
            'extension' => $extension,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }

    /**
     * @return array{kind: MediaKind, mime: string, extension: string, width: ?int, height: ?int}|null
     */
    private function describeVideo(UploadedFile $file): ?array
    {
        $mime = $this->detectMime($file);
        $extension = $mime !== null ? (self::VIDEO_TYPES[$mime] ?? null) : null;

        if ($mime === null || $extension === null) {
            return null;
        }

        return [
            'kind' => MediaKind::Video,
            'mime' => $mime,
            'extension' => $extension,
            'width' => null,
            'height' => null,
        ];
    }

    private function detectMime(UploadedFile $file): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = @finfo_file($finfo, $file->getPathname());
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream'
            ? strtolower($mime)
            : null;
    }
}
