<?php

declare(strict_types=1);

namespace Tests\Feature\Social;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Social\Models\Follow;
use App\Domain\Social\Services\MentionSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "@" picker.
 *
 * The ordering is the feature, so the ordering is what these test: that the
 * people you follow come before the people you do not, and that a famous
 * stranger still beats an obscure one among the strangers.
 */
final class MentionSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/users/mention-suggestions';

    public function test_following_accounts_come_before_strangers(): void
    {
        $viewer = User::factory()->create();

        // Alphabetically the stranger sorts first, so a naive ordering would
        // put him at the top. The point is that the followed account wins.
        $stranger = User::factory()->create(['username' => 'aaa_stranger']);
        $followed = User::factory()->create(['username' => 'zzz_friend']);

        Follow::query()->create([
            'follower_id' => $viewer->id,
            'following_id' => $followed->id,
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $followed->id)
            ->assertJsonPath('data.users.0.is_following', true)
            ->assertJsonPath('data.users.1.id', $stranger->id)
            ->assertJsonPath('data.users.1.is_following', false);
    }

    public function test_bare_at_sign_returns_followed_accounts(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create(['username' => 'friend_of_mine']);
        User::factory()->create(['username' => 'celebrity']);

        Follow::query()->create([
            'follower_id' => $viewer->id,
            'following_id' => $followed->id,
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $followed->id);
    }

    public function test_among_strangers_the_more_followed_account_wins(): void
    {
        $viewer = User::factory()->create();
        $famous = User::factory()->create(['username' => 'zzz_famous']);

        $this->giveFollowers($famous, 25);

        $obscure = User::factory()->create(['username' => 'aaa_obscure']);
        $this->giveFollowers($obscure, 1);

        Sanctum::actingAs($viewer);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $famous->id)
            ->assertJsonPath('data.users.1.id', $obscure->id);
    }

    public function test_suggestions_are_filtered_by_the_typed_term(): void
    {
        $viewer = User::factory()->create();
        $match = User::factory()->create(['username' => 'amit_dev', 'display_name' => 'Amit Dev']);
        User::factory()->create(['username' => 'someone_else']);

        Sanctum::actingAs($viewer);

        $this->getJson(self::ENDPOINT.'?query=amit')
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.id', $match->id);
    }

    public function test_the_viewer_is_never_suggested(): void
    {
        $viewer = User::factory()->create(['username' => 'myself']);

        Sanctum::actingAs($viewer);

        $this->getJson(self::ENDPOINT.'?query=myself')
            ->assertOk()
            ->assertJsonCount(0, 'data.users');
    }

    public function test_inactive_accounts_are_not_suggested(): void
    {
        $viewer = User::factory()->create();
        $suspended = User::factory()->create(['username' => 'banned_person']);
        $suspended->forceFill(['status' => UserStatus::Suspended->value])->save();

        Sanctum::actingAs($viewer);

        $this->getJson(self::ENDPOINT.'?query=banned')
            ->assertOk()
            ->assertJsonCount(0, 'data.users');
    }

    public function test_a_wildcard_term_does_not_match_everyone(): void
    {
        $viewer = User::factory()->create();
        User::factory()->count(3)->create();

        Sanctum::actingAs($viewer);

        // "%" is a LIKE wildcard. If it were passed through raw it would
        // return every account in the table.
        $this->getJson(self::ENDPOINT.'?query=%25')
            ->assertOk()
            ->assertJsonCount(0, 'data.users');
    }

    public function test_the_limit_is_honoured(): void
    {
        $viewer = User::factory()->create();
        User::factory()->count(6)->create();

        Sanctum::actingAs($viewer);

        $this->getJson(self::ENDPOINT.'?limit=3')
            ->assertOk()
            ->assertJsonCount(3, 'data.users');
    }

    public function test_suggestions_require_authentication(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    /**
     * The parser is the other half of the feature: what the picker offers is
     * only useful if what the user actually typed is what gets stored.
     */
    public function test_mentions_are_parsed_out_of_the_caption(): void
    {
        $this->assertSame(
            ['amit', 'sara'],
            MentionSuggestionService::parse('hello @amit and @sara'),
        );
    }

    public function test_parsing_is_case_insensitive_and_deduplicates(): void
    {
        $this->assertSame(
            ['amit'],
            MentionSuggestionService::parse('@Amit @amit @AMIT'),
        );
    }

    public function test_an_email_address_is_not_a_mention(): void
    {
        $this->assertSame([], MentionSuggestionService::parse('write to me@example.com'));
    }

    public function test_trailing_punctuation_is_trimmed(): void
    {
        $this->assertSame(
            ['amit', 'sara'],
            MentionSuggestionService::parse('@amit, @sara.'),
        );
    }

    public function test_a_bare_at_sign_yields_nothing(): void
    {
        $this->assertSame([], MentionSuggestionService::parse('sending this @ '));
    }

    private function giveFollowers(User $user, int $count): void
    {
        $followers = User::factory()->count($count)->create();

        foreach ($followers as $follower) {
            Follow::query()->create([
                'follower_id' => $follower->id,
                'following_id' => $user->id,
            ]);
        }
    }
}
