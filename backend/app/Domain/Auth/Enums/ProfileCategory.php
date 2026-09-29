<?php

declare(strict_types=1);

namespace App\Domain\Auth\Enums;

/**
 * The public creator categories a Business/Professional account can pick
 * from. These power the profile badge ("Artist", "Entertainment", …) and are
 * served by `GET /api/v1/categories` so the client never hard-codes them.
 */
enum ProfileCategory: string
{
    case Artist = 'artist';
    case Entertainment = 'entertainment';
    case Sport = 'sport';
    case Creator = 'creator';
    case Music = 'music';
    case Food = 'food';
    case Fashion = 'fashion';
    case Beauty = 'beauty';
    case Travel = 'travel';
    case Tech = 'tech';
    case Education = 'education';
    case Business = 'business';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Artist => 'Artist',
            self::Entertainment => 'Entertainment',
            self::Sport => 'Sport',
            self::Creator => 'Creator',
            self::Music => 'Music',
            self::Food => 'Food',
            self::Fashion => 'Fashion',
            self::Beauty => 'Beauty',
            self::Travel => 'Travel',
            self::Tech => 'Tech',
            self::Education => 'Education',
            self::Business => 'Business',
            self::Other => 'Other',
        };
    }
}
