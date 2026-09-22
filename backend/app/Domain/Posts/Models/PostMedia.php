<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Domain\Posts\Enums\PostMediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $post_id
 * @property-read PostMediaType $type
 * @property-read string $file_path
 * @property-read string|null $mime
 * @property-read int $size
 * @property-read int|null $width
 * @property-read int|null $height
 * @property-read int|null $duration
 * @property-read int $sort_order
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class PostMedia extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'type',
        'file_path',
        'mime',
        'size',
        'width',
        'height',
        'duration',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PostMediaType::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
