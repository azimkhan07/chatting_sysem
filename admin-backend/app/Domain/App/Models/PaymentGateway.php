<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Payment gateway credential, admin-managed via CRUD. Changing a gateway only
 * means writing its key/secret/endpoint here - never code.
 *
 * @property int $id
 * @property string $name
 * @property string|null $key
 * @property string|null $merchant_id
 * @property string|null $secret
 * @property string|null $endpoint
 * @property string $currency
 * @property bool $enabled
 */
final class PaymentGateway extends Model
{

    protected $connection = 'app';
    protected $fillable = ['name', 'key', 'merchant_id', 'secret', 'endpoint', 'currency', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}