# 06 — API Design

Base path: `https://api.<domain>/api/v1` (local: `http://127.0.0.1:8000/api/v1`).

## Principles

1. **JSON everywhere.** Content-Type: application/json.
2. **Ready-to-render:** server sends projections (labels, avatars, urls, counts, unread
   totals) — never IDs the client must look up. Data is computed server-side.
3. **Versioned** `v1`. Breaking changes → `v2`, old stays for deprecation window.
4. **Methods by semantics** — GET (read), POST (create/act), PATCH/PUT (update), DELETE.
5. **Idempotency keys** on creates where a retry must not double-charge/double-post
   (billing, posts): header `Idempotency-Key`.

## Response envelope

```json
{
  "data": {},
  "meta": { "request_id": "abc123", "pagination": null },
  "errors": []
}
```

- Success = `data` present, `errors: []`.
- Paginated lists use `meta.pagination`:

```json
"pagination": {
  "next_cursor": "eyJ0cyI6IjIwMjYtMDktMTdUMTA6MDA6MDBaIn0=",
  "has_more": true,
  "total": 1240
}
```

Cursor is opaque (base64 of ordering key). Client passes it back verbatim —
no math, no page numbers. This is what makes big lists (feed, chat) hang-proof.

## Error format

```json
{
  "data": null,
  "errors": [{
    "code": "VALIDATION_ERROR",
    "message": "The caption field is required.",
    "field": "caption"
  }],
  "meta": { "request_id": "def456" }
}
```

| HTTP | code                 | meaning |
| ---- | -------------------- | ------- |
| 400  | VALIDATION_ERROR     | form/body invalid; `field` present |
| 401  | UNAUTHENTICATED      | missing/invalid token |
| 403  | FORBIDDEN            | authed but not allowed |
| 404  | NOT_FOUND            | resource missing (or hidden) |
| 409  | CONFLICT             | duplicate/lock/hang (e.g. already following) |
| 429  | TOO_MANY_REQUESTS    | rate-limited (Retry-After) |
| 422  | UNPROCESSABLE        | domain rule failed (e.g. pay failed) |
| 503  | UNAVAILABLE          | maintenance / degraded |

Request ID flows into logs & traces for correlation.

## Auth

- Route group `auth:sanctum`; Bearer token (SPA cookie-session optional later).
- Endpoints returning private data require auth; public profile/feed reads allowed but
  throttled.
- CSRF irrelevant for token APIs; CORS locked to the web origin + app schemes.

## Core routes (v1 draft)

### Auth `/auth`
```
POST /auth/register          {username,email,password,name}
POST /auth/login             {email,password}
POST /auth/logout
GET  /auth/me                → profile + unread totals (server-computed)
```

### Profiles & follows `/users/{username}`
```
GET  /users/{username}                 → profile (follow state for requester)
PATCH /users/me                        → update bio/avatar/cover (multipart)
POST /users/{username}/follow          → idempotent
DELETE /users/{username}/follow
GET  /users/{username}/followers?cursor
GET  /users/{username}/following?cursor
GET  /users/{username}/media?cursor    → their posts/reels
```

### Feed `/feed`
```
GET /feed?cursor                 → cursor feed: own posts + followed authors' posts
POST /posts                      → create post (multipart media) [Idempotency-Key]
GET  /posts/{post}               → single post detail
PATCH /posts/{post}              → edit caption
DELETE /posts/{post}
POST /posts/{post}/like          → idempotent
DELETE /posts/{post}/like
GET  /posts/{post}/comments?cursor   → root comments only; each carries `replies` (max 3) + `reply_count`
POST /posts/{post}/comments      → {body, parent_id?} — `parent_id` must reference a
                                    root comment on the same post (replies-to-replies → 422)
POST /posts/{post}/share         {to:"conversation_id"|null} → group/DM/feed share
```

### Reels `/reels`
```
POST /reels                     → upload video (multipart) [Idempotency-Key]
GET  /reels/{reel}              → the stored file's playback URL
GET  /reels/trending?cursor
GET  /reels/feed?cursor         → vertical stream
```
v1 has no transcode stage: `POST /reels` stores the content-sniffed upload and returns it
ready to play (progressive file). There is no `status: processing` poll and no manifest/poster
response yet — see `08-media-pipeline.md`.

### Explorer `/explore`
```
GET /explore?cursor             → trending grid (Redis zset)
GET /search?q=&type=users|posts|tags&cursor
GET /hashtags/{tag}
```

### Chat `/chat`
```
GET  /chat/conversations?cursor        → with last_message + unread_count (server-computed)
POST /chat/conversations               {type:"dm",user_id} | {type:"group",name,member_ids[]}
GET  /chat/conversations/{c}/messages?cursor
POST /chat/conversations/{c}/messages  {type,body|media_url} [Idempotency-Key]
POST /chat/conversations/{c}/read      {up_to_message_id}   → recompute unread server-side
PATCH /chat/conversations/{c}          {muted}
PATCH /chat/conversations/{c}/personalize {nickname?,wallpaper_key?}  → viewer's own copy only
POST /chat/conversations/{c}/drawings  multipart drawing                → {media_url,mime}
POST /chat/conversations/{c}/members   (group)
DELETE /chat/conversations/{c}/members/{user} (group)
POST /chat/presence                    → heartbeat; {is_online,last_seen_at}
GET  /chat/entitlements                → {features[{key,label,blurb,unlocked}],unlocked[],wallpapers[]}
POST /chat/conversations/{c}/request/accept   → recipient only; requested → active
DELETE /chat/conversations/{c}/request        → recipient only; deletes the spam thread
GET  /chat/groups/{c}/invite           → active invite or null
POST /chat/groups/{c}/invite           → create (idempotent: reuses a live link)
DELETE /chat/groups/{c}/invite         → revoke
POST /chat/invites/{code}/join         → conversation (idempotent for members)
```
`ConversationResource` also carries `state` (`active|requested`), `is_request_actionable` (true
only for the recipient), and the viewer's private `my_nickname` / `my_wallpaper_key`.

### Subscription-gated chat
Locked capabilities return `403` with code `FEATURE_LOCKED` and
`details.feature = <key>`, so the client can put the crown on that exact control:
```
{ "code":"FEATURE_LOCKED", "message":"GIF messages needs an active subscription.",
  "details": { "feature":"chat_gif", "blurb":"..." } }
```
Gated routes: `PATCH /conversations/{c}/personalize` (`chat_nickname`),
`POST /conversations/{c}/drawings` (`chat_drawing`). `chat_gif` and `chat_drawing` are enforced
in the service when a message is sent; `message_requests` is enforced inside `startDm` only on the
branch that would create a request, so following someone and messaging them stays free.

### Blue tick `/subscriptions`
```
POST /subscriptions/verify      → start verification request
POST /subscriptions/checkout    {plan:"amtech_basic"|"amtech_pro"} [Idempotency-Key]
POST /subscriptions/{id}/pay    {gateway,"token"}  (mock in v1)
GET  /subscriptions/{id}        → payment/verification status
DELETE /subscriptions/{id}      → cancel auto-renew
GET  /subscriptions/tiers       → plan catalog lookup
```

### Notifications `/notifications`
```
GET  /notifications?cursor
POST /notifications/read-all
GET  /notifications/unread-total      → Redis-backed counter
```

## Realtime contract

- WebSocket endpoints under `/app` via **Reverb**; channel naming:
  - private-user.{user_id}  → notifications, presence tells
  - private-dm.{conv_id}    → DM messages
  - private-group.{conv_id} → group messages/typing
  - presence-dm.{conv_id} / presence-group.{conv_id} → online members in a thread
- WS is **best-effort**: DB is truth. Client must re-sync via REST on reconnect/resume
  (e.g. fetch `/chat/conversations`, `/messages` after cursor, `/unread-total`).
- Presence is also polled: `ConversationResource` carries `online_count`,
  `peer_presence` and `members[].user.is_online`, so a client with a dead socket
  still renders correct dots on the next inbox fetch.

## Pagination rules (enforced)

- All lists: `?cursor=...` (+ optional `?limit=10..50`, default 20).
- No `offset/limit` math on public routes. No `ORDER BY RAND()` anywhere.

## Rate limits (Redis)

| Route group        | Limit                    |
| ------------------ | ------------------------ |
| auth (register/login) | 10/min/IP + 30/day/IP  |
| feed/explore       | 120/min/user            |
| search             | 60/min/user             |
| chat send          | 300/min/user            |
| subscriptions/pay  | 10/min/user + idempotency |

## Media upload contract

- **Shipped (v1):** single-request multipart upload. The server sniffs the real content type,
  enforces the size/type limits and stores the file on the `public` disk; the response already
  carries a playable `/storage/...` URL. Video limit is 100 MB, image 8 MB, ≤ 5 files per post.
- **Target:** two-step for video (reels) — `POST /uploads` → presigned destination → chunked PUT →
  `POST /reels {upload_token}` → server transcodes on the `media` queue, returns
  `status: processing`; client polls `GET /reels/{id}` until `ready`. Not implemented.

## OpenAPI

- `routes/api.php` annotations + `resources/views/api` generated spec served at
  `/docs/api` in dev/staging. CI enforces the spec never regresses (contract test).