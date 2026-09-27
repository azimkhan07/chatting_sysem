<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Enums\MessageType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SubscribesUsers;
use Tests\TestCase;

final class ChatDrawingTest extends TestCase
{
    use RefreshDatabase;
    use SubscribesUsers;

    public function test_a_subscriber_can_upload_a_drawing_and_send_it(): void
    {
        Storage::fake('public');
        [$me, $conversation] = $this->dmFor($this->subscriber());

        $upload = $this->actingAs($me)
            ->post("/api/v1/chat/conversations/{$conversation->id}/drawings", [
                'drawing' => $this->png(),
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['media_url', 'mime']])
            ->json('data');

        $this->assertStringStartsWith('/storage/drawings/', $upload['media_url']);

        $this->actingAs($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'type' => MessageType::Drawing->value,
                'media_url' => $upload['media_url'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.message.type', 'drawing')
            ->assertJsonPath('data.message.media_url', $upload['media_url']);
    }

    public function test_a_free_account_cannot_upload_a_drawing(): void
    {
        Storage::fake('public');
        [$me, $conversation] = $this->dmFor();

        $this->actingAs($me)
            ->post("/api/v1/chat/conversations/{$conversation->id}/drawings", [
                'drawing' => $this->png(),
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FEATURE_LOCKED')
            ->assertJsonPath('errors.0.details.feature', 'chat_drawing');
    }

    public function test_a_renamed_non_image_is_rejected(): void
    {
        Storage::fake('public');
        [$me, $conversation] = $this->dmFor($this->subscriber());

        $this->actingAs($me)
            ->post("/api/v1/chat/conversations/{$conversation->id}/drawings", [
                'drawing' => UploadedFile::fake()->createWithContent(
                    'sketch.png',
                    "<?php echo 'not an image';",
                ),
            ])
            ->assertStatus(422);
    }

    public function test_a_stranger_cannot_upload_into_someone_elses_conversation(): void
    {
        Storage::fake('public');
        [, $conversation] = $this->dmFor();

        $this->actingAs($this->subscriber())
            ->post("/api/v1/chat/conversations/{$conversation->id}/drawings", [
                'drawing' => $this->png(),
            ])
            ->assertNotFound();
    }

    public function test_a_gif_needs_the_gif_feature(): void
    {
        [$me, $conversation] = $this->dmFor();

        $this->actingAs($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'type' => MessageType::Gif->value,
                'media_url' => 'https://media.example.com/party.gif',
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.0.details.feature', 'chat_gif');
    }

    #[DataProvider('unsafeMediaReferences')]
    public function test_unsafe_media_references_are_rejected(string $reference): void
    {
        [$me, $conversation] = $this->dmFor($this->subscriber());

        $this->actingAs($me)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'type' => MessageType::Image->value,
                'media_url' => $reference,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'media_url');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeMediaReferences(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'data uri' => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='],
            'protocol relative' => ['//evil.example/tracker.png'],
            'backslash trick' => ['/\\evil.example/tracker.png'],
            'traversal' => ['/storage/../../config.php'],
            'other scheme' => ['file:///etc/passwd'],
        ];
    }

    /**
     * A real 1x1 PNG, built from bytes rather than
     * `UploadedFile::fake()->image()` so the suite does not need GD.
     */
    private function png(): UploadedFile
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true,
        );
        $path = (string) tempnam(sys_get_temp_dir(), 'drawing-').'.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'sketch.png', 'image/png', null, true);
    }

    /**
     * @return array{0: User, 1: Conversation}
     */
    private function dmFor(?User $first = null): array
    {
        $first ??= User::factory()->create();
        $second = User::factory()->create();

        $conversation = Conversation::query()->create([
            'type' => 'dm',
            'created_by' => $first->id,
        ]);

        foreach ([$first, $second] as $member) {
            ConversationMember::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $member->id,
            ]);
        }

        return [$first, $conversation];
    }
}
