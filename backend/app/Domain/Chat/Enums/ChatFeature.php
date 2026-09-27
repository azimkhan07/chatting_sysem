<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

/**
 * Chat capabilities that an active subscription unlocks.
 *
 * The client renders a crown/lock on every locked control, but the lock is only
 * decoration: each of these is also enforced on the route that performs the
 * action, so a locked feature cannot be reached by calling the API directly.
 */
enum ChatFeature: string
{
    /** Dedicated request inbox so strangers cannot reach the primary chat. */
    case MessageRequests = 'message_requests';

    /** Per-conversation nickname, local to the person who set it. */
    case Nickname = 'chat_nickname';

    /** Per-conversation wallpaper, built-ins plus gallery picks. */
    case Wallpaper = 'chat_wallpaper';

    /** GIF messages. */
    case Gif = 'chat_gif';

    /** Freehand drawing messages rendered to an image. */
    case Drawing = 'chat_drawing';

    /** Pinning a message to the top of a conversation for everyone in it. */
    case PinnedMessages = 'chat_pinned_messages';

    public function label(): string
    {
        return match ($this) {
            self::MessageRequests => 'Message requests',
            self::Nickname => 'Chat nicknames',
            self::Wallpaper => 'Chat wallpapers',
            self::Gif => 'GIF messages',
            self::Drawing => 'Drawing messages',
            self::PinnedMessages => 'Pinned messages',
        };
    }

    /** One line shown on the locked control so the paywall explains itself. */
    public function blurb(): string
    {
        return match ($this) {
            self::MessageRequests => 'Strangers land in a separate request inbox instead of your chats.',
            self::Nickname => 'Give each conversation a name only you can see.',
            self::Wallpaper => 'Built-in and gallery wallpapers for every conversation.',
            self::Gif => 'Search and send GIFs straight from the composer.',
            self::Drawing => 'Sketch a message and send it as an image.',
            self::PinnedMessages => 'Pin the messages that matter to the top of the chat for everyone.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $feature): string => $feature->value,
            self::cases(),
        );
    }
}
