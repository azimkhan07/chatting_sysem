<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMessage;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed aggregation for the chat inbox, so loading a large inbox
 * (100+ conversations) and the unread badge never scans the full
 * conversation_messages table.
 *
 * - `chat:unread:{userId}`   hash   {conversationId: unreadCount}
 * - `chat:last:{conversationId}`  string  JSON snapshot of the last message
 *
 * Every method degrades to SQL if Redis is unavailable; call sites keep their
 * existing queries as the source of truth and only overlay these fast paths.
 */
final class ChatInboxCache
{
    private const UNREAD_PREFIX = 'chat:unread:';

    private const LAST_PREFIX = 'chat:last:';

    private const LAST_TTL_SECONDS = 86_400;

    /**
     * Record a freshly sent message: refresh the last-message snapshot and
     * increment the unread counter for every member except the sender.
     */
    public function noteNewMessage(Conversation $conversation, ConversationMessage $message): void
    {
        try {
            $redis = Redis::connection();

            $conversation->members
                ->where('user_id', '!=', (int) $message->user_id)
                ->each(function ($member) use ($redis, $conversation): void {
                    $redis->hincrby(self::UNREAD_PREFIX.$member->user_id, (string) $conversation->id, 1);
                });

            $this->setLast($redis, $conversation->id, $message);
        } catch (\Throwable) {
            // Redis unreachable: DB remains the source of truth.
        }
    }

    /**
     * Zero the user's unread counter for a conversation (after a read).
     */
    public function markRead(int $userId, int $conversationId): void
    {
        try {
            Redis::connection()->hdel(self::UNREAD_PREFIX.$userId, (string) $conversationId);
        } catch (\Throwable) {
            // Best effort.
        }
    }

    /**
     * Per-conversation unread counts for a user, or null when Redis is
     * unavailable or the hash has never been written.
     *
     * @return array<int, int>|null conversationId => unread
     */
    public function unreadMap(int $userId): ?array
    {
        try {
            /** @var array<string, string> $raw */
            $raw = Redis::connection()->hgetall(self::UNREAD_PREFIX.$userId);
        } catch (\Throwable) {
            return null;
        }

        if ($raw === []) {
            return null;
        }

        $map = [];
        foreach ($raw as $conversationId => $unread) {
            $map[(int) $conversationId] = (int) $unread;
        }

        return $map;
    }

    /**
     * Total unread across all conversations, or null when the hash is absent.
     */
    public function unreadTotal(int $userId): ?int
    {
        $map = $this->unreadMap($userId);

        if ($map === null) {
            return null;
        }

        return array_sum($map);
    }

    /**
     * Last-message snapshot for a conversation (id, body, type, media_url,
     * user_id, created_at), or null when not cached.
     *
     * @return array<string, mixed>|null
     */
    public function lastSnapshot(int $conversationId): ?array
    {
        try {
            $json = Redis::connection()->get(self::LAST_PREFIX.$conversationId);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($json) || $json === '') {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Prime the per-user unread hash so badge reads never touch SQL again.
     *
     * @param  array<int, int>  $counts  conversationId => unread
     */
    public function warmUnread(int $userId, array $counts): void
    {
        try {
            $redis = Redis::connection();
            $key = self::UNREAD_PREFIX.$userId;

            foreach ($counts as $conversationId => $unread) {
                $redis->hset($key, (string) $conversationId, (int) $unread);
            }
        } catch (\Throwable) {
            // Best effort.
        }
    }

    /**
     * Drop the cached snapshot for a conversation (message deleted).
     */
    public function forgetLast(int $conversationId): void
    {
        try {
            Redis::connection()->del(self::LAST_PREFIX.$conversationId);
        } catch (\Throwable) {
            // Best effort.
        }
    }

    private function setLast($redis, int $conversationId, ConversationMessage $message): void
    {
        $snapshot = [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'type' => $message->type->value,
            'body' => $message->body,
            'media_url' => $message->media_url,
            'created_at' => $message->created_at?->toIso8601String(),
        ];

        $redis->setex(self::LAST_PREFIX.$conversationId, self::LAST_TTL_SECONDS, (string) json_encode($snapshot));
    }
}
