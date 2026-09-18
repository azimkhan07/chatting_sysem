# 10 — Performance & Caching

The promise: **a user with many groups, big feeds and huge chats never hangs.**

## The strategy (four layers)

### 1. Don't fetch what we don't render — projections

- Every list endpoint selects only the columns needed for the item card.
- Resources are eager-loaded; `PreventLazyLoading` is ON in production (`Model::preventLazyLoading`).
- Never `select *` for cards. Never `with([])` a nesting we won't show.

### 2. Don't recompute what we already know — Redis cache

| Pattern                       | Example |
| ----------------------------- | ------- |
| Hot immutable-ish reads       | profile cards, media meta — TTL + tagged invalidation |
| Aggregates/counters           | `unread:{user}` via event-incremented Redis counter + DB reconcile |
| Ranking                      | `trending` zset recomputed on schedule (5 min) + score decay |
| Identity lookups              | username→user id map, hashtag→posts (Redis sets, capped) |
| Session/token check offload   | Sanctum tokens verified against Redis first, DB fallback |

Cache policy:
- Cache keys centralized in `Services/RedisCache` (prefixes + version bump).
- **Tagged invalidation**: `feed` tag on user's followers → publish → drop affected
  feed keys. Categories: `feed`, `profile`, `media`, `chat`, `billing`.
- Memoize expensive row-computation (e.g. "who can see this post" ACL) with short TTL.

### 3. Distribute the work — queues

Everything heavy leaves the request thread:

- Media ingest/transcode → `media` queue.
- Feed fan-out (write new post id into followers' Redis zsets) → `feed` queue.
- Notification build + WS + (later) push → `notify` queue.
- Blue-tick renewals/expiry sweeps → `billing` queue/schedule.
- Analytics flush, cache warm-up → `default` queue / scheduler.

Backpressure: `queue:monitor`, dead-letter, retry with exponential backoff, `ShouldBeUnique`
with `dispatchAfterResponse` for duplicate suppression. Failed jobs visible in Horizon-like
dashboard at `/horizon` (dev).

### 4. Don't let clients do O(n) — cursor pagination + aggregation

- Deep page = same O(1) cost (indexed cursor, not OFFSET).
- Chat list query: one eager-loaded read that returns last-message + unread per row
  (no nested per-conversation COUNT loops):
  ```php
  conversation_members
    └─join conversations, messages (last per conv via window fn), members (other user)
  ```
- Messages: page 50 with `(conversation_id, id DESC)` index. 10k-message scroll = 10k
  virtualization on the client, 50-row queries server-side.

## Feed ranking (server-side, cached)

```
score(post, viewer) =
   recency_decay(created_at)
 + engagement(post)                    // like+2, comment+3, share+4 — normalized
 + affinity(viewer, author)            // follow + interactions, from interaction log
 + diversity_penalizer                 // no author repeats within 7 items
 + anti_spam_penalty                   // frequency breach / report weight
```
- Compute happens in a cacheable `FeedBuilder` (Redis zset per user, refreshed every ~60s
  and on new post fan-out with 10% probabilistic refresh).
- Client never ranks; it just renders the returned order.

## The stress marker tests

Backend tests that gate CI performance regressions:

```
SeedUserInManyGroups: 1 user, 100 groups, each 1k messages (100k rows)
 └─ GET /chat/conversations → assert page < 150ms DB time
SeedBigFeed: 10k posts from 2k authors
 └─ GET /feed?cursor → assert < 200ms DB time (Redis warm)
```
Marked `@group stress`, run on CI nightly + on performance branches.

## Database tuning checklist (prod)

- Indexes: `messages(conversation_id, id)`, `posts(user_id, id)`, `comments(post_id, id)`,
  `follow(follower_id)`, `messages(sender_id, created_at)`.
- Counters (likes/comment/shares) are maintained in the same DB transaction as the
  event — consistent and cheap. Analytics-style numbers (views) are eventually consistent
  via Redis flush.
- PgBouncer/pgpool scaling for postgres connections (v2+); read replicas (v3).

## Redis memory & reliability

- `maxmemory-policy allkeys-lru` with graceful degrade checks (cacheable only paths).
- Unread counter drift watchdog: reconcile job every 15 min recomputes from watermarks.
- If Redis down: reads go to DB (slightly slower, still fast due to indexing); queues
  pause with `drain` reports; chat WS doesn't fan-out but REST works.

## Logging & observability

- Request ID on every log line; structured JSON logs.
- Key RED metrics: route p95, queue depths, Redis hit-rate, DB query-count per request.
- Slow-query logger (> threshold) + N+1 lint enforced in CI (Pint/psalm ruleset catches
  lazy load in tests via `preventLazyLoading`).