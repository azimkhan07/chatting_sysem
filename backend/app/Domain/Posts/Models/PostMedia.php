<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Domain\Posts\Enums\PostMediaType;
use Illuminate\Database\Eloquent\Model;

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
