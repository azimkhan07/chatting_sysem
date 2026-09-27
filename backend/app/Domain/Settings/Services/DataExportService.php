<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\PostMedia;

/**
 * Builds a portable copy of one user's data.
 *
 * Secret material is excluded on purpose: `password` and every Sanctum token
 * are not "the user's data" in a GDPR sense, they are credentials, and an
 * export file travels. Everything here is what the user can see in the app.
 */
final class DataExportService
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $user->loadMissing(['posts.media', 'followers', 'following']);
        $settings = app(SettingsService::class);

        return [
            'export_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'bio' => $user->bio,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'account_type' => $user->account_type->value,
                'contact_email' => $user->contact_email,
                'contact_phone' => $user->contact_phone,
                'show_contact' => $user->show_contact,
                'is_verified' => $user->is_verified,
                'status' => $user->status->value,
                'member_since' => $user->created_at->toIso8601String(),
            ],
            'counts' => [
                'posts' => $user->posts->count(),
                'followers' => $user->followers->count(),
                'following' => $user->following->count(),
                'conversations' => $user->conversations()->count(),
                'subscriptions' => $user->subscriptions()->count(),
            ],
            'posts' => $user->posts->map(static fn (Post $post): array => [
                'id' => $post->id,
                'body' => $post->body,
                'created_at' => $post->created_at?->toIso8601String(),
                'media' => $post->media->map(static fn (PostMedia $media): array => [
                    'type' => $media->type,
                    'file_path' => $media->file_path,
                    'mime' => $media->mime,
                ])->all(),
            ])->all(),
            'connections' => [
                'followers' => $user->followers->map(static fn (User $follower): array => [
                    'username' => $follower->username,
                    'display_name' => $follower->display_name,
                ])->all(),
                'following' => $user->following->map(static fn (User $following): array => [
                    'username' => $following->username,
                    'display_name' => $following->display_name,
                ])->all(),
            ],
            'settings' => $settings->grouped($settings->for($user)),
        ];
    }
}
