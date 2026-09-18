# 05 — Data Model

Source of truth: `backend/database/migrations`. This doc is the blueprint → keep in sync.

Conventions:
- **ULID** primary keys for all public-facing rows (no sequential IDs leak, no enumeration).
- Timestamps everywhere. Soft deletes on user content (moderation friendly).
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
| status            | enum      | active | suspended | banned    |
| account_type      | enum      | personal | creator | org     |
| last_seen_at      | datetime  | for online hint fallback     |
| timestamps        |           | + soft deletes               |

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

| Column  | Type   | Notes |
| ------- | ------ | ----- |
| id      | ulid   |       |
| post_id | FK     |       |
| user_id | FK     |       |
| parent_id | FK   | one-level nesting (later depth) |
| body    | text   |       |
| deleted_at | soft |     |

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

| Column     | Type   | Notes |
| ---------- | ------ | ----- |
| id         | ulid   |       |
| type       | enum   | dm | group |
| created_by | FK     |       |

### conversation_members (DM = 2 rows, Group = N rows)

| Column        | Type   | Notes |
| ------------- | ------ | ----- |
| conversation_id | FK   |       |
| user_id       | FK     |       |
| role          | enum   | owner | admin | member |
| last_read_message_id | FK | watermark for unread calc |
| is_muted      | bool   |       |
| joined_at     | datetime |  |

### messages

| Column           | Type    | Notes |
| ---------------- | ------- | ----- |
| id               | ulid    |       |
| conversation_id  | FK      | index(conversation_id, id DESC) |
| sender_id        | FK      |       |
| type             | enum    | text | image | video | audio | system |
| body             | text    | text messages / captions |
| media_path       | string  | + thumbnail for media |
| reply_to_id      | FK      | optional |
| edited_at        | datetime|null |
| deleted_at       | soft    | system tombstone for everyone |
| created_at       | datetime| message ordering key |

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

Unread badge = count where read_at null (Redis-accelerated, same pattern as chat).

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
```

## Migration & seeding rules

- Every migration denormalised counter is introduced **along with its backfill**.
- Seeders idempotent + `php artisan db:seed --class=DemoSeeder` gives a rich demo scene
  (100 users, mix of posts/reels, groups, messages, blue-tick users) for local + staging.
- **Stress seeder** (`--class=StressSeeder`): one user in 100 groups, 10k messages —
  feeds the no-hang marker tests.