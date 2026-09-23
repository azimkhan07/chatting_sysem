<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Threads\Models\Thread;
use App\Domain\Threads\Models\ThreadEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreadEntry>
 */
final class ThreadEntryFactory extends Factory
{
    protected $model = ThreadEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'user_id' => 1,
            'body' => fake()->sentence(),
        ];
    }
}
