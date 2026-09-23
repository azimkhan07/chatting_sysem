<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ConversationType::Group,
            'name' => fake()->words(2, true),
            'created_by' => User::factory(),
        ];
    }
}
