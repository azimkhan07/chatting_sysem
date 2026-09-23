# 07 — Realtime Chat

Chat must feel instant, yet scale: **many groups × many members × many messages** must
never hang any user. Realtime only accelerates delivery; Postgres is the source of truth.

## Stack

- **Laravel Reverb** with the Laravel Broadcasting layer over **Redis** pub/sub.
- Sanctum guards channel auth.
- Client library: `laravel-echo`.

## Channel map

| Channel                     | Who can join        | Payload |
| --------------------------- | ------------------- | ------- |
| `private-user.{user_id}`    | that user           | notifications, billing, presence hints |
| `private-dm.{conv_id}`      | 2 members           | messages, read receipts, typing |
| `private-group.{conv_id}`   | group members       | messages, typing, member join/leave (system msg) |
| `presence-{group_id}`       | group members       | online roster per group |

> **Implemented:** `NotificationCreated` (ShouldBroadcastNow) publishes on the
> `private-user.{user_id}` channel after every persisted notification (follow, like,
> comment, verified). Channel auth is enforced server-side by `App\Broadcasting\UserChannel`.

## Message lifecycle

```
Sender SPA ──POST /chat/conversations/{c}/messages──▶ API(validate→CreateMessageAction)
                                                       │  persist message (DB truth)
                                                       │  publish domain Event MessageSent
                                                       ▼
                                          ┌────────────┴────────────┐
                                          │ Redis unread++ (for other members)   │
                                          │ Reverb broadcast → online members     │
                                          │ Queue job → push/receipt to offline   │
                                          ▼
                                   Returning senders get full message in response
```

- Online members receive instantly via WS (they also see their unread counter bump from
  Redis via `private-user` channel or when they re-fetch).
- Offline members get it on next fetch (REST cursor) and later push notification (v2).

## Read receipts & unread (the hang-proof core)

- Each member row holds `last_read_message_id` (watermark) in `conversation_members`.
- On `POST .../read {up_to_message_id}`:
  1. Update watermark.
  2. Compute `delta = messages > old_watermark` (DB count, but limited window).
  3. Redis: `unread:{user} -= delta` ; persisted receipts written for audit.
- Global unread for the chat tab = `unread:{user}` (CDN-cached, evicted on change).
- A reconciliation worker recomputes totals from DB periodically (self-heal if Redis
  flushed) and writes back.

Why this never hangs:
- We never send the client the full message corpus to count; the client never O(n) sums.
- Message list loads are **cursor-paginated**, server-side eager-loaded, capped at
  50/page; group list is eager-loaded with last message + unread only.
- `GROUP BY` unread math only ever touches member watermarks, not every row.

## Typing indicator

- Throttled (1 per 2s per conv): `typing:conv:{id}` Redis key + broadcast to channel.
- Client shows "typing…" for max 3s or until stop key clears.

## Presence

- Reverb `PresenceChannel` gives join/leave atomically.
- Redis `presence:online` set mirrors it for non-group availability dots.
- `last_seen_at` in DB is the fallback for profile view (no WS).

## Reconnect/resync contract

- On `disconnected` → client pauses sends, shows subtle banner.
- On reconnect: client fetches `GET /chat/conversations`, then unread-total, then latest
  pending since last ack via cursor. No message loss — DB truth wins.

## Scaling path (v3 → services/)

- Reverb scales horizontally with a connection scheduler + Redis adapter.
- Chat service extracted when: >5k concurrent WS or chat API p95 > 300ms.
- Message writes may move behind an append-only log; reads served from replicas.

## Failure isolation

- If Redis is down: WS degrades (best-effort), REST still fully works (DB truth).
- If Reverb is down: REST + polling fallback; presence stale-tolerant.
- Queue worker down → notifications/messages still in DB; delivery retried with backoff.