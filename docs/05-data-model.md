# 05 — Data Model

Source of truth: `backend/database/migrations`. This doc is the blueprint → keep in sync.

Conventions:
- **ULID** primary keys for public-facing rows (no sequential IDs leak, no enumeration).
  Known exception: `comments.id` is a plain auto-increment `bigint`, as are the join/counter
  tables — always check the migration before relying on a key type.
- Timestamps everywhere. Soft deletes on user content (moderation friendly). `comments` is the
  exception: it has no `deleted_at` in v1.
- Money as integer **paise** (₹). `amount_paisa`.
- Reads are cheap because we eagerly load projections; **derived columns stored on purpose**
  (counters) only where a Redis counter would be lossy for correctness (e.g. likes).

## users

| Column            | Type      | Notes                        |
| ----------------- | --------- | ---------------------------- |
| id                | ulid      | PK                           |
| username          | unique    | permalink, handles           |
| email             | unique    | login                        |
| password          | hashed    |                              |
| display_name      | string    |                              |
| bio               | text/null |                              |
| avatar_path       | string/null | object-store path          |
| cover_path        | string/null |                             |
| is_verified       | boolean   | tick visible = subscription active |
| status            | enum      | `active` / `suspended` / `banned` (see `App\Domain\Auth\Enums\UserStatus`) |
| account_type      | enum      | `personal` / `professional` / `business` (see `App\Domain\Auth\Enums\AccountType`) |
| contact_email     | string/null | **publicly listed** contact address, only when published |
| contact_phone     | string/null | **publicly listed** contact number, only when published |
| show_contact      | boolean   | gate for the two columns above |
| deactivated_at    | datetime/null | self-service deactivate; reversible. Deliberately *not* a `status` value — a suspension is admin-only and final, whereas a deactivation can be undone with a password |
| last_seen_at      | datetime  | for online hint fallback     |
| timestamps        |           | + soft deletes               |

`email` / `mobile` are owner-only. `UserResource` never publishes them; the public profile
exposes `contact_email` / `contact_phone` instead, and only for a professional or business
account with `show_contact = true`. Downgrading to `personal` wipes the contact columns and
unsets the flag, so a leftover contact can never outlive the account type that justified it.


### subscriptions (blue tick)

| Column        | Type    | Notes |
| ------------- | ------- | ----- |
| id            | ulid    |       |
| user_id       | FK      |       |
| plan          | enum    | amtech_basic (₹1) | amtech_pro (₹5) |
| amount_paisa  | int     | 100 or 500           |
| status        | enum    | pending | active | expired | cancelled | refunded |
| verified_at / approved_by | | admin review result |
| starts_at / expires_at / auto_renew | | renewal cycle |
| payment_token | string  | gateway reference |

Side effect: when subscription is active → `users.is_verified = true` +
unique badge on profile. Expiry job downgrades.

## follow

| Column      | Type | Constraints |
| ----------- | ---- | ----------- |
| follower_id | FK   | PK(follower_id, following_id) |
| following_id| FK   |                             |

## posts

| Column       | Type   | Notes |
| ------------ | ------ | ----- |
| id           | ulid   |       |
| user_id      | FK     |       |
| type         | enum   | post | reel | poll |
| caption      | text   | + hashtags extracted (tag table) |
| location     | string | optional |
| status       | enum   | draft | published | archived | removed |
| likes_count   | int    | maintained counter |
| comments_count| int   | maintained counter |
| shares_count  | int    | maintained counter |
| is_pinned    | bool   | profile pin |

### post_media

| Column        | Type         | Notes |
| ------------- | ------------ | ----- |
| id            | ulid         |       |
| post_id       | FK           |       |
| media_type    | enum         | image | video |
| display_order | tinyint      | carousel order |
| original_path / processed_path | | object store |
| thumbnail_path | string      | for video/poster |
| width/height/duration_ms | | meta |
| status        | enum         | pending | ready | failed |

### likes

PK(post_id, user_id) — no duplicates. Counter kept on posts.

### comments

| Column     | Type    | Notes |
| ---------- | ------- | ----- |
| id         | bigint  | auto-increment (not a ULID) |
| post_id    | FK      | cascade delete |
| user_id    | FK      | cascade delete |
| parent_id  | FK      | nullable; one-level nesting enforced in the application layer (`CommentOnPostAction` rejects a reply to a reply), `nullOnDelete`, indexed with `post_id` |
| body       | text    | |
| timestamps |         | |

Replies are never returned as standalone rows: `GET posts/{post}/comments` returns root
comments only, each with a `replies` preview (max 3) and the total `reply_count`. There is
no `deleted_at` on `comments` in v1 — a comment is deleted by deleting the row.

### shares

user_id, post_id — like source for share-tree analytics (v2).

### hashtags

| Column    | Type   | Notes |
| --------- | ------ | ----- |
| tag       | string | unique key |
| post_count| int    | Redis-accelerated |
| pivot: post_hashtags(post_id, tag_id, creator_id) | | |

## Chat module

### conversations

| Column       | Type   | Notes |
| ------------ | ------ | ----- |
| id           | ulid   |       |
| type         | enum   | dm \| group |
| state        | enum   | active \| requested — `requested` = a DM from a non-follower waiting for accept |
| requested_by | FK     | nullable; the sender of a pending request (the only side with actions) |
| created_by   | FK     |       |

### conversation_members (DM = 2 rows, Group = N rows)

| Column        | Type   | Notes |
| ------------- | ------ | ----- |
| conversation_id | FK   |       |
| user_id       | FK     |       |
| role          | enum   | owner \| admin \| member |
| last_read_message_id | FK | watermark for unread calc |
| is_muted      | bool   |       |
| nickname      | string | nullable, **private to this row's owner** — premium, never exposed to other members |
| wallpaper_key | string | nullable, built-in gradient key, **private to this row's owner** — premium |
| joined_at     | datetime |  |

### messages

| Column           | Type    | Notes |
| ---------------- | ------- | ----- |
| id               | ulid    |       |
| conversation_id  | FK      | index(conversation_id, id DESC) |
| sender_id        | FK      |       |
| type             | enum    | text \| image \| video \| gif \| drawing \| system — gif/drawing are premium |
| body             | text    | text messages / captions |
| media_path       | string  | + thumbnail for media |
| media_url        | string  | nullable external source (a provider GIF, or the `/storage/...` a drawing upload returned); validated by `MediaUrl::isSafeReference()` |
| reply_to_id      | FK      | optional |
| pinned_at        | datetime|null | set when a member pins the message; `null` means unpinned |
| pinned_by        | FK      | nullable; who pinned it, so the pin can be attributed and revoked |
| edited_at        | datetime|null |
| deleted_at       | soft    | system tombstone for everyone |
| created_at       | datetime| message ordering key |

**Pinned messages:** index `(conversation_id, pinned_at)`. `MessagePinService` is the only
writer; it refuses when the caller is not a member, when the message is deleted, and when
the account lacks the `chat_pinned_messages` entitlement (`403 FEATURE_LOCKED` carrying the
feature key, so the client puts the crown on that one control). `GET .../pins` returns the
newest pin first. Only one pin is surfaced in the conversation bar; the full list is behind
the bar, so a chat with 40 pins does not push the composer off screen.

**Unread computation (the hang-proof part):**
- Per member: `last_read_message_id` watermark.
- Unread = count of messages after watermark in that conversation — always computed
  server-side, never by summing client state.
- Global unread = Redis counter per user, updated on `MessageSent` (increment) and
  `Read` (decrement by delta); DB reconciliation job runs periodically to self-heal.

### read_receipts (delivered vs read)

| Column    | Type | Notes |
| --------- | ---- | ----- |
| message_id / user_id | | PK composite; read_at filled on read |

## Vice versa: media/upload

Uses object storage (S3-compatible) behind `Filesystem::disk('media')`. DB stores paths +
metadata in `post_media`. Signed/transient URLs for private content (attachments in DMs).

## Notification module

| Column   | Type   | Notes |
| -------- | ------ | ----- |
| id       | ulid   |       |
| user_id  | FK     |      |
| title/body | string|    |
| type     | string  | like | comment | follow | share | system | billing |
| data     | json    | payload for deep link |
| read_at  | datetime|null |
| link     | string  | route on client |

Unread badge = count where read_at null (Redis-accelerated, same pattern for chat).

## Billing

| Column         | Type   | Notes |
| -------------- | ------ | ----- |
| id             | ulid   |       |
| user_id        | FK     |       |
| subscription_id| FK     |       |
| gateway        | enum   | upi | card | wallet (v1: mock) |
| gateway_ref    | string | server-side reference |
| amount_paisa   | int    |       |
| status         | enum   | initiated | success | failed | refunded |
| currency       | string | INR   |

## Account Center module

### user_settings

Privacy and notification preferences, one row per user, created lazily on first read by
`SettingsService::for()` so a user who never opens Settings still has a complete, defaulted
row instead of a pile of null checks at every call site.

| Column | Type | Notes |
| ------ | ---- | ----- |
| id | bigint | |
| user_id | FK | unique |
| discoverable | bool | search + Explore |
| show_activity_status | bool | |
| allow_message_requests | bool | |
| allow_tagging | bool | |
| notify_messages / notify_requests / notify_follows / notify_likes / notify_comments | bool | |

`UserSettings::preferenceGroups()` is the single list of legal keys; `UpdateSettingsRequest`
derives its rules from it, so adding a preference to the schema and validating it are one
change rather than two. Unknown keys are rejected instead of silently dropped, so a typo in
a client payload is visible rather than a preference that never saves.

Sessions are **not** a table — they are Sanctum `personal_access_tokens`, and the token name
carries the user agent so the sessions list can say "Android" instead of "Unknown device".

## Family Center module

| family_groups | Type | Notes |
| ------------- | ---- | ----- |
| id | bigint | |
| owner_id | FK | cascade delete; not fillable, only `FamilyService` writes it |
| name | string(60) | |

| family_members | Type | Notes |
| -------------- | ---- | ----- |
| id | bigint | |
| family_group_id | FK | cascade delete |
| user_id | FK | **unique** — one family per user, enforced by the index |
| role | enum | `guardian` / `adult` / `teen` |
| timestamps | | join order |

Two invariants hold everywhere and are re-checked rather than assumed:
1. The owner is always a guardian and can never be removed, demoted or renamed around.
2. A family therefore always has at least one guardian.

Permission matrix (the membership row is the only authority — there is no separate
"is this user a parent" flag to fall out of sync):

| Action | guardian (non-owner) | owner | adult | teen |
| ------ | -------------------- | ----- | ----- | ---- |
| rename / dissolve family | no | yes | no | no |
| add adult or teen | yes | yes | yes | no |
| add another guardian | no | yes | no | no |
| change a non-guardian role | yes | yes | yes | no |
| change or remove a guardian | no | yes | no | no |
| remove a non-guardian | yes | yes | yes | no |
| approve spending | yes | yes | no | no |
| leave | yes | n/a | yes | yes |

Members are added **by username**, with no pending-invite step: it matches the supervision
use case (a guardian setting up a teen's account) and keeps the schema free of tokens that
have to expire, be resent, or leak. A consent-pending flow is a deliberate follow-up.

"Someone else's family" and "no such family" both return `404 NOT_FOUND`, so the endpoint
cannot be used to discover that a given household exists. A suspended or deactivated account
cannot be added.

Deleting an account clears the membership: soft delete keeps the `users` row, so the FK
cascade never fires and the membership would otherwise survive as a ghost in someone else's
roster. If the deleted account owned the family, the family is dissolved too — leaving a
household headless would leave the remaining members with nobody who can manage them.

## ERD (summary)

```
users ──< posts ──< post_media
  │        ├──< likes
  │        ├──< comments
  │        └──< shares
  │        └──> hashtags (pivot)
users ──< follow (self-referential)
users ──< conversations >< conversation_members >< users
conversations ──< messages
users ──< subscriptions (blue tick) ──< payments
users ──< notifications
users ──1 user_settings
users ──1 family_members >──1 family_groups ──> users (owner)
```

## Migration & seeding rules

- Every migration denormalised counter is introduced **along with its backfill**.
- Seeders idempotent + `php artisan db:seed --class=DemoSeeder` gives a rich demo scene
  (100 users, mix of posts/reels, groups, messages, blue-tick users) for local + staging.
- **Stress seeder** (`--class=StressSeeder`): one user in 100 groups, 10k messages —
  feeds the no-hang marker tests.