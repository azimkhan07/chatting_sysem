<?php

declare(strict_types=1);

namespace App\Domain\Email\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row email transport config (id always 1). Admin writes host/port/
 * credentials via CRUD; the composer reads this per send, so changing a mail
 * server never needs a redeploy.
 *
 * @property int $id
 * @property string|null $host
 * @property int $port
 * @property string|null $username
 * @property string|null $password
 * @property string $encryption
 * @property string|null $from_email
 * @property string|null $from_name
 * @property bool $enabled
 */
final class EmailConfig extends Model
{
    protected $table = 'email_configs';

    protected $fillable = [
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_email',
        'from_name',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}