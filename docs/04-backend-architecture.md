# 04 — Backend Architecture (Laravel)

## Layout

```text
backend/app/
├─ Domain/                       # bounded contexts (business logic lives HERE)
│  ├─ Auth/                      # registration, login, tokens
│  ├─ Profile/                   # profiles, follow system, verification badge
│  ├─ Feed/                      # posts, feed ranking, likes, comments, shares
│  ├─ Media/                     # upload orchestration, deliver URLs
│  ├─ Reels/                     # video pipeline, views tracking
│  ├─ Chat/                      # conversations, groups, messages, read state
│  ├─ Notification/              # notification generation & delivery
│  └─ Billing/                   # blue-tick subscriptions ₹1/₹5
│     ├─ Actions/                # single-purpose service classes (e.g. CheckoutPostAction, PublishPostAction)
│     ├─ Contracts/              # interfaces — the ONLY cross-module surface + triggering services
│     ├─ Enums/
│     ├─ Models/                 # Eloquent models (thin)
│     └─ Events/                 # domain events (PostPublished, MessageSent, VerificationApproved)
├─ Http/
│  ├─ Controllers/               # THIN: validate → call Action → return Resource
│  ├─ Resources/                 # API projections (ready-to-render shapes)
│  ├─ Requests/                  # FormRequests (validation)
│  └─ Middleware/                # auth, throttle, verified-user
├─ Services/
│  ├─ RedisCache/                # cache keys, tagged invalidation
│  ├─ Media/                     # storage disks, thumbnails, signed URLs
│  └─ Realtime/                  # presence manager, typing events
└─ Support/                      # helpers (Ids, Pagination), base classes
```

## Rules (senior-level, non-negotiable)

1. **Controllers are thin.** A controller: instantiate Action → call it → return a
   `Resource`. No business logic in controllers.
2. **Actions are the unit of work.** One class = one use case, e.g.
   `CreateGroupAction`, `PayForVerificationAction`. Everything hard is in an Action.
3. **Modules talk via Contracts** (`Interfaces/`), not by importing other module's
   Eloquent models directly. This is what makes microservices extraction mechanical.
4. **Domain events for side effects** (notify, feed fan-out, media job dispatch). The
   Action publishes an event; listeners dispatch Queues.
5. **Queries are eager-loaded everywhere.** Never an N+1. Use `with()`, `load()`,
   `withCount()`, and **`select` only needed columns**.
6. **Resources (projections) are the only output shape.** Controllers never return raw
   models that leak internal fields. Pagination is always a page object.
7. **Caching is explicit.** Cache keys centralised in `RedisCache`. Invalidation tagged.
   Cache reads must never be the source of truth for correctness.
8. **Heavy work is queued.** Media processing, feed fan-out, notifications, webhook-like
   calls. Request path stays fast.
9. **A performed operation is testable alone.** Each Action has a Feature/Fast test.

## Eager loading strategy ("the no-hang guarantee")

Audience-supplied rule: *API computes everything server-side; a user with many groups
must never hang due to messages.*

Implementation safety rails:
- **Repository/Query helpers** that always join the required relations:
  `ChatQueries::conversationsFor($user)` — returns conversations with `lastMessage`,
  `unreadCount`, `otherParticipant`, `groupSummary` in ONE query set (eager loaded).
- **Aggregate caching with Redis**: per-user unread total = Redis counter kept in sync by
  a `MessageSent`/`MessageRead` subscriber + DB recompute job on drift detection.
- **Cursor pagination** everywhere lists can be big (feed, chat, messages, notifications).
  No `page`-based page jitter; deep pages stay O(1)-ish.
- **Deferred projection**: `->cursor()` + `Resource` collection with `preventLazyLoading`.

Dashboard example — chat list response shape (all server-computed):

```json
{
  "conversations": [{
    "id": "c_9f2...",
    "unread_count": 3,
    "last_message": { "body": "Hi! ✅", "sent_at": "2026-09-17T10:00:00Z", "sender_name": "Ayaan" },
    "other": { "username": "ayaan", "avatar": "/u/ayaan/avatar.jpg", "is_online": true }
  }]
}
```

## Redis usage map

| Key space                        | Purpose                     | TTL / invalidation |
| -------------------------------- | --------------------------- | ------------------ |
| `feed:{user}`                    | ranked feed item IDs        | 60s, tagged `feed`  |
| `unread:{user}`                  | total unread counter        | event-driven sync  |
| `trending` (zset)                | explorer/trending ranking   | 5 min recompute    |
| `presence:online` (set)          | who is online               | event-driven       |
| `rate:{route}:{user}`            | rate limiting buckets       | 60s                |
| `media:*`                        | video HLS manifest/posters  | long, purgeable    |
| `queue:default|media|feed|notify`| queue brokers               | n/a (jobs ack)     |

## Queue topology

| Queue         | Consumers           | Work                                  |
| ------------- | ------------------- | ------------------------------------- |
| `media`       | 2+ workers (CPU)    | transcode reels, thumbnail/image gen, upload to CDN/object store |
| `feed`        | 2+ workers          | feed fan-out writes (Redis zsets)     |
| `notify`      | 2+ workers          | build + push notifications, WS events |
| `billing`     | 1 worker            | renewals, expiry sweeps, receipts     |
| `default`     | 2+ workers          | everything else (deferred)            |

Jobs are idempotent (re-run safe) — use `unique()` guards and `ShouldBeUnique` where
ordering matters (e.g. duplicate notification suppression).

## Wiring realtime (Reverb)

- Auth channel auth via `broadcastAuth` + Sanctum (private/groups/presence).
- Events: `Chat\Events\MessageSent`, `Typing`, `PresenceJoined/Left`,
  `NotificationPushed`, `Feed\Events\PostPublished`, `Reels\Events\ViewsTicked`.
- The client treats WS as best-effort; on reconnect it re-syncs from the API
  (source of truth remains Postgres). See `07-realtime-chat.md`.

## Testing pyramid

- **Unit/Fast**: Actions, enums, pure math (helper → `unit`).
- **Feature**: route → controller → action → DB state → resource shape.
- **Stress**: seeded 100-group/10k-message user; assert API page stays < X ms (marker test).
- **E2E (smoke)**: Playwright on the SPA against seeded staging.