<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * A console session token, stored in the console database.
 *
 * Eloquent binds a related model's connection to its parent's when the related
 * model declares none, but the token is looked up *before* a parent is known —
 * Sanctum's guard reads the row straight off a hash, then resolves the owner
 * from `tokenable_type`. Left alone it would use this app's default connection
 * and look for console sessions in whatever database the default points at.
 *
 * Pinning `admin` is the point of the split: a session token for the console is
 * a credential, and the main app has no connection string for this database.
 * `tokenable_type` therefore also always names an `Admin\...` class here — it
 * can never resolve to an app user, because app users do not exist in this app.
 */
final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'admin';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Left as Eloquent's default morphTo on purpose. An override that pins the
     * related class looks like it adds safety, but it also has to reproduce the
     * relationship's own type resolution, and a version that gets it subtly
     * wrong fails closed: every authenticated request 401s while login keeps
     * working, which reads as "the tokens are broken" rather than "the
     * relation is".
     *
     * The property that actually matters here needs no help. `tokenable_type`
     * is written by `HasApiTokens` from the owning model's class name, and
     * StaffUser is the only model in this app that uses the trait, so the only
     * value that can be in that column is this one. An app user cannot appear
     * there even if a row were tampered with, because the app's user model is
     * not a tokenable in this process at all.
     */
}
