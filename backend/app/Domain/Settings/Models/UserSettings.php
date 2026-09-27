<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Privacy and notification preferences.
 *
 * Created lazily on first read, so a user who never opens Settings still has a
 * complete, defaulted row to reason about instead of a pile of null checks at
 * every call site.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $discoverable
 * @property bool $show_activity_status
 * @property bool $allow_message_requests
 * @property bool $allow_tagging
 * @property bool $notify_messages
 * @property bool $notify_requests
 * @property bool $notify_follows
 * @property bool $notify_likes
 * @property bool $notify_comments
 */
class UserSettings extends Model
{
    /**
     * The only columns a client may write. Everything else is derived.
     *
     * @var list<string>
     */
    protected $fillable = [
        'discoverable',
        'show_activity_status',
        'allow_message_requests',
        'allow_tagging',
        'notify_messages',
        'notify_requests',
        'notify_follows',
        'notify_likes',
        'notify_comments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discoverable' => 'boolean',
            'show_activity_status' => 'boolean',
            'allow_message_requests' => 'boolean',
            'allow_tagging' => 'boolean',
            'notify_messages' => 'boolean',
            'notify_requests' => 'boolean',
            'notify_follows' => 'boolean',
            'notify_likes' => 'boolean',
            'notify_comments' => 'boolean',
        ];
    }

    /**
     * The settable preferences, grouped the way the client groups them. Used
     * by the request validator so the wire format and the schema cannot drift.
     *
     * @return array<string, list<string>>
     */
    public static function preferenceGroups(): array
    {
        return [
            'privacy' => [
                'discoverable',
                'show_activity_status',
                'allow_message_requests',
                'allow_tagging',
            ],
            'notifications' => [
                'notify_messages',
                'notify_requests',
                'notify_follows',
                'notify_likes',
                'notify_comments',
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
