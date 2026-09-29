<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Enums;

/**
 * Why someone is reporting something.
 *
 * An enum rather than free text because this list is also the moderator's
 * triage filter: a queue sorted by "this is a child-safety report" is
 * actionable, and one sorted by whatever string the reporter typed is not. It
 * is deliberately short. Twelve reasons get twelve actions out of staff; forty
 * get a free-text field that is never read.
 */
enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Abuse = 'abuse';
    case HateSpeech = 'hate_speech';
    case Nudity = 'nudity';
    case SelfHarm = 'self_harm';
    case Scam = 'scam';
    case FakeAccount = 'fake_account';
    case Other = 'other';

    /**
     * Reasons that put a child at risk, and therefore jump the queue.
     *
     * The two that are not "this is unpleasant" but "someone may be in
     * danger" are separated from the rest on purpose: sorting by priority is
     * the difference between a queue someone works top-down and one they
     * work oldest-first.
     */
    public function isUrgent(): bool
    {
        return match ($this) {
            self::SelfHarm, self::Abuse => true,
            default => false,
        };
    }

    /**
     * The label the client shows. Kept here so the reason a reporter picks
     * and the reason a moderator reads are the same wording.
     */
    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam or scam',
            self::Harassment => 'Harassment',
            self::Abuse => 'Abusive content',
            self::HateSpeech => 'Hate speech',
            self::Nudity => 'Nudity or sexual content',
            self::SelfHarm => 'Self-harm or danger',
            self::Scam => 'Fraud or impersonation',
            self::FakeAccount => 'Fake account',
            self::Other => 'Something else',
        };
    }
}
