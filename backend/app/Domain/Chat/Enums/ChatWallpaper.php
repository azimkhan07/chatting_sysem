<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

/**
 * The built-in wallpaper set shipped with v1. Each case is a gradient the
 * client renders locally, so wallpapers cost no storage, no bandwidth and no
 * moderation surface. Gallery uploads extend this in a later phase.
 */
enum ChatWallpaper: string
{
    case Solid = 'solid';
    case Aurora = 'aurora';
    case Sunset = 'sunset';
    case Ocean = 'ocean';
    case Neon = 'neon';
    case Blush = 'blush';
    case Forest = 'forest';
    case Graphite = 'graphite';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            static fn (self $wallpaper): string => $wallpaper->value,
            self::cases(),
        );
    }
}
