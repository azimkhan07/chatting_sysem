<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

/**
 * Chat capabilities that an active subscription unlocks.
 *
 * The client renders a crown/lock on every locked control, but the lock is only
 * decoration: each of these is also enforced on the route that performs the
 * action, so a locked feature cannot be reached by calling the API directly.
 *
 * This enum is the single registration point for a feature. Adding a case here
 * is the whole job: {@see \App\Domain\Chat\FeatureCatalogueSync} materialises
 * it into the `features` table on the next boot, which is what the admin
 * console's subscription form renders as a checkbox. There is no second list to
 * update and no seed array to append to - see FeatureCatalogueSync for what that
 * replaced.
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

            // The fallback is load-bearing, not laziness. An exhaustive `match`
            // with no default throws UnhandledMatchError on the first case
            // somebody adds without also editing this method - and the catalogue
            // sync runs on boot, so that mistake would take the whole application
            // down on the next deploy over a missing string. A half-finished
            // registration must show up as a slightly ugly label in the admin
            // form, not as a fatal error. Curate the label here when the keyword
            // deserves better wording.
            default => $this->humanisedKeyword(),
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

            // See the note on label(). No blurb simply means the admin form shows
            // the feature with no description, which is a normal state; it must
            // not be a fatal error.
            default => '',
        };
    }

    /**
     * Paid, or free for everyone.
     *
     * The default is free and no case is listed yet. Adding an enum case is
     * meant to be the whole registration step, and a new case that silently
     * arrived as a paid feature would lock itself out of every existing
     * subscriber's account the day it was merged. A feature becomes premium by
     * being named here deliberately, when somebody has decided what it costs.
     */
    public function tier(): FeatureTier
    {
        return match ($this) {
            // Marked paid as they are actually charged for.
            default => FeatureTier::Free,
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

    /**
     * `chat_pinned_messages` -> `Chat pinned messages`.
     *
     * Used as the label for a feature that has not been given curated wording,
     * so an uncurated feature still reads as English in the admin form instead
     * of showing a raw keyword to somebody deciding what to charge for.
     */
    private function humanisedKeyword(): string
    {
        $words = str_replace(['_', '-'], ' ', $this->value);

        return ucwords($words);
    }
}
