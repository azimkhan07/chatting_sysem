<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Facades\Storage;

final class MediaUrl
{
    /**
     * URL for a file stored on the public disk. Root-relative unless
     * ASSET_URL is configured, so clients never receive a host-specific URL.
     */
    public static function of(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public static function ofNullable(?string $path): ?string
    {
        return is_string($path) && $path !== '' ? self::of($path) : null;
    }

    /**
     * Whether a client-supplied media reference is safe to store and later hand
     * straight to a client as an image/video/GIF source.
     *
     * Accepted:
     *  - absolute `http`/`https` URLs (a GIF picked from a provider, an
     *    externally hosted clip);
     *  - root-relative `/storage/...` references, which is exactly what our own
     *    upload endpoints return, so a freshly uploaded drawing can be sent back
     *    without the client having to know the asset host.
     *
     * Rejected: `javascript:` and `data:` (script execution and inline payload
     * smuggling), protocol-relative `//evil.example` (silently absolute in a
     * browser), backslash tricks (`/\evil.example`) and any other scheme.
     */
    public static function isSafeReference(mixed $reference): bool
    {
        if (! is_string($reference) || $reference === '' || strlen($reference) > 500) {
            return false;
        }

        // Reject control characters and backslashes outright: they are only
        // ever used to confuse a URL parser into treating a relative path as
        // an absolute one.
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $reference) === 1) {
            return false;
        }

        if (str_starts_with($reference, '/storage/')) {
            return ! str_contains(substr($reference, 1), '..');
        }

        return filter_var($reference, FILTER_VALIDATE_URL) !== false
            && in_array(
                strtolower((string) parse_url($reference, PHP_URL_SCHEME)),
                ['http', 'https'],
                true,
            );
    }
}
