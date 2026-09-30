<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

/**
 * The one token table is the app's, even for console staff.
 *
 * Eloquent binds a related model's connection to the parent's when the related
 * model does not declare one. A console staff account (on the `admin`
 * connection) would therefore write and look up its Sanctum tokens in the
 * console database, while Sanctum's guard resolves tokens from the app
 * connection — the two never meeting. Pinning the app connection here keeps a
 * single shared `personal_access_tokens` table that both staff and app tokens
 * use; `tokenable_type` still resolves back to the console `StaffUser`.
 */
final class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    protected $connection = 'sqlite';
}