<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Chat\Models\Conversation;
use App\Domain\Threads\Enums\ThreadStatus;
use App\Domain\Threads\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thread>
 */
final class ThreadFactory extends Factory
{
    protected $model = Thread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'created_by' => 1,
            'status' => ThreadStatus::Active,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => ThreadStatus::Expired,
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
