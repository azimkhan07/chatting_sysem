<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedHeader(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, ['Authorization' => 'Bearer '.$token]];
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->patchJson('/api/v1/me', ['bio' => 'hello'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_user_can_update_bio_and_display_name(): void
    {
        [$user, $headers] = $this->authenticatedHeader();

        $response = $this->patchJson('/api/v1/me', [
            'display_name' => '  Ayaan Khan  ',
            'bio' => '  Building amteCHAT 🚀  ',
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('data.user.display_name', 'Ayaan Khan')
            ->assertJsonPath('data.user.bio', 'Building amteCHAT 🚀');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'display_name' => 'Ayaan Khan',
            'bio' => 'Building amteCHAT 🚀',
        ]);
    }

    public function test_user_can_clear_bio(): void
    {
        $user = User::factory()->create(['bio' => 'old bio']);
        $token = $user->createToken('test')->plainTextToken;

        $this->patchJson('/api/v1/me', ['bio' => null], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.user.bio', null);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'bio' => null]);
    }

    public function test_display_name_validation_is_enforced(): void
    {
        [, $headers] = $this->authenticatedHeader();

        $this->patchJson('/api/v1/me', ['display_name' => 'a'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'display_name');
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        [, $headers] = $this->authenticatedHeader();

        $response = $this->postJson('/api/v1/me/avatar', [
            'image' => UploadedFile::fake()->create('avatar.jpg', 60, 'image/jpeg'),
        ], $headers);

        $avatarUrl = $response->json('data.user.avatar_url');
        $response->assertOk()
            ->assertJsonPath('data.user.avatar_url', fn ($url) => is_string($url) && str_contains($url, 'storage/'));

        Storage::disk('public')->assertExists('avatars/'.basename((string) $avatarUrl));
    }

    public function test_uploading_avatar_replaces_the_previous_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', 'old');

        $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/me/avatar', [
            'image' => UploadedFile::fake()->create('avatar.jpg', 60, 'image/jpeg'),
        ], ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        Storage::disk('public')->assertMissing('avatars/old.jpg');
    }

    public function test_user_can_upload_cover(): void
    {
        Storage::fake('public');
        [$user, $headers] = $this->authenticatedHeader();

        $response = $this->postJson('/api/v1/me/cover', [
            'image' => UploadedFile::fake()->create('cover.png', 120, 'image/png'),
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('data.user.cover_url', fn ($url) => is_string($url));

        $this->assertNotEmpty($user->refresh()->cover_path);
    }

    public function test_image_upload_rejects_non_image_files(): void
    {
        [, $headers] = $this->authenticatedHeader();

        $this->postJson('/api/v1/me/avatar', [
            'image' => UploadedFile::fake()->create('malware.txt', 100, 'text/plain'),
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'image');
    }
}
