<?php

namespace Database\Factories;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The model this factory produces (domain namespace, not App\Models).
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->optional()->numerify('##########'),
            'display_name' => fake()->name(),
            'password' => static::$password ??= 'password',
            'is_verified' => false,
            'status' => 'active',
        ];
    }

    /**
     * Reuse the same email for a second account (engagement play, v1).
     *
     * @return $this
     */
    public function sharingEmail(string $email): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => $email,
        ]);
    }
}
