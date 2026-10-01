<?php

declare(strict_types=1);

namespace Admin\Support\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Asset URLs for the console.
 *
 * The console reads media out of the main app's storage layout so an avatar in
 * a report queue renders without a second round trip. It never writes here.
 */
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
}
