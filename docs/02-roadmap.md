# 02 — Roadmap

Phased delivery. Each milestone ends with a **working demo in production staging** and a
**test suite that passes**.

---

## Phase 0 — Foundation (current)

**Goal:** A boring, solid base we can build fast on.

- [x] Monorepo: `backend/` (Laravel) + `frontend/` (React) + `services/` + `docker/` + `docs/`
- [x] Root git repo + `.gitignore`
- [x] GitHub remote → push → **CI/CD green on first push** (lint + test + build)
- [x] Local dev UX: `composer install`, `npm install`, both servers on one command
- [x] GitHub Actions: PHPUnit + Pint + PHPStan + `tsc` + `vite build`
- [x] Docker Compose for local: `mysql` (or postgres), `redis`

**Exit criteria:** Fresh clone → CI green in <10 min locally, Jenkins-free.

---

## Phase 1 — v1 Web MVP

**Goal:** A real, safe, usable product the public can sign up to.

Ordered by dependency, each item shipped with its own tests + docs update:

1. **Identity**
   - Sanctum (or JWT-free token) auth: register, login, logout, me.
   - [x] Suspended/banned accounts are refused at login **and** on every authenticated API call
     (`EnsureUserIsActive` after `auth:sanctum`); the offending token is revoked on use.
   - [x] Avatar/cover upload — content-sniffed, size/dimension capped, safe replace
     (new file written first, old one deleted only after success).
   - [ ] Image resize + thumbnail variants (media pipeline v2, see `08-media-pipeline.md`).
2. **Profiles & Follow**
   - [x] Profiles, bio, followers/following, follow/unfollow (server-computed counts).
   - [x] Followers/following list modal + clickable counters on `Profile` / `UserProfile`;
     list payload carries `is_followed_by_me` so rows can show a follow control.
3. **Feed (Home)**
   - [x] Posts (text/image/video), cursor pagination, infinite scroll.
   - [x] Like / unlike, share count, and **one-level nested comments** — `comments.parent_id`,
     root-only listing with an inline reply preview + `reply_count`, replies to a reply
     rejected, reply notifications to the comment author.
   - [ ] Server-ranked feed ordering — v1 ships a straightforward cursor feed (own posts +
     followed authors); the ranking/fan-out layer is Phase 3 scale work, not v1.
4. **Reels**
   - [x] Upload with content sniffing, full-screen vertical player, autoplay muted,
     like/comment/share, reels tab on Home.
   - [ ] Server transcodes (rotation) + HLS + poster. **Not built.** v1 stores and plays the
     uploaded file progressively; see the v1/target table in `08-media-pipeline.md`.
5. **Explore**
   - [x] Search users/hashtags — `GET /users/search` (prefix-then-contains, LIKE wildcards
      escaped, 8 results) and `GET /hashtags/search`; Explore switches tabs and falls back to
      the trending grid when the query is empty.
   - [x] Trending grid — Redis sorted set `posts:trending` (like=1/comment=2/share=4), bumped in realtime, `posts:refresh-trending` every 5 min, DB-ranked fallback, `GET posts/trending`, Explore tab toggle.
6. **Chat (1:1 + Groups)**
   - Reverb WebSockets: messages, typing, presence, read receipts.
   - Groups: create/join/invite, roles (owner/admin/member), shareable invite links + realtime join.
   - [x] Full-page chat layout — inbox rail + thread fill the whole body beside the sidebar; mobile responsive with bottom tab bar and back-navigation thread.
   - [x] Message reactions (6 emojis, toggle/switch, realtime via Reverb) + delete-for-everyone (sender or group moderator).
   - [x] Typing indicator — server-throttled `POST conversations/{id}/typing` + Reverb `UserTyping`; thread header shows "X is typing…" (group: "N people…") with a 3.5s expiry and clears when the message lands.
   - [x] Read receipts as DP (Instagram/Messenger style) — watermark-based `read` + `read_by` in `MessageResource`; UI draws reader DPs on the last read message instead of a ✓✓ double tick.
   - [x] **Stress case:** a user with 100+ groups gets a snappy inbox — `ChatInboxCache` (Redis
      per-user unread hash + last-message snapshots), single-pass grouped SQL fill with DB fallback.
   - [x] Presence / online status — Redis `presence:online` zset (90s window) with `last_seen_at`
     DB fallback, `POST /chat/presence` heartbeat, `UserPresenceChanged` (`presence.changed`) on
     DM/group channels, `presence-{dm|group}.{id}` rosters, `chat:presence-sweep` every 30s,
     `online_count` / `peer_presence` / `members[].user.is_online` in `ConversationResource`,
     and client dots + "Last seen …" in the inbox and thread header.
   - [x] **Spam control: message requests** — a DM from a non-follower is stored as
     `state = requested` instead of a live chat. Recipient-only `accept` / `delete` (delete cascades
     members + messages), a dedicated Requests tab in the inbox, an in-thread banner, and a disabled
     composer until accepted. Accept/Delete stay available after a subscription lapses so a
     legitimate request is never stuck.
   - [x] **Premium chat tier** (subscription-gated, backend-enforced) — `ChatFeature` catalogue
     (`message_requests`, `chat_nickname`, `chat_wallpaper`, `chat_gif`, `chat_drawing`),
     `GET /chat/entitlements`, `EnsureChatFeature` route middleware and `ChatEntitlements::authorize`
     in the service layer, `FEATURE_LOCKED` errors carrying the feature key, and crown/lock UI that
     names the exact locked feature. Free accounts keep follow-DM, groups, reactions and typing.
   - [x] Per-chat personalization — private `nickname` + `wallpaper_key` on `conversation_members`
     (never exposed to the other person), one sheet for both, 8 built-in gradient wallpapers.
   - [x] Richer composer — emoji picker (insert at caret), GIF search (existing `/songs/gifs`),
     and drawings: canvas → PNG → `POST /chat/conversations/{id}/drawings` (byte-sniffed like every
     other upload) → `drawing` message. `MediaUrl::isSafeReference()` allows only `http(s)` and our
     own `/storage/...`, rejecting `javascript:`, `data:`, `//host` and backslash tricks.
   - [ ] Gallery wallpaper uploads and AI-generated wallpapers — deferred: needs a per-user
     wallpaper asset table, upload quota, and moderation review before anything user-supplied
     becomes a chat background.
   - [x] Invite flows hardened on the client — two-step revoke confirm, expiry/creator line,
     clipboard fallback for non-https origins, error banners with retry, and a join page that
     handles missing codes, real API failures (429/403/5xx) and retries.
   - [x] **Pinned messages** (subscription-gated) — `pinned_at` / `pinned_by` on `messages`,
     `MessagePinService` as the only writer, `GET .../pins` plus pin/unpin endpoints, a pinned
     bar in the conversation and a bubble-level pin control. Locked accounts get
     `FEATURE_LOCKED` carrying `chat_pinned_messages`, so the crown lands on that one control.

7. **Blue Tick**
   - Request verification → pay ₹1/₹5 (UPI/Mock gateway v1) → admin review → badge.
   - Auto-renew + expiry. Cancellation removes tick.
   - [x] Cancellation removes the tick immediately when the last active subscription is cancelled;
     auto-renew/expiry covered by feature tests.
   - [x] Admin review panel at `/admin` (separate surface, own auth/store): dashboard of per-status
     counts + net revenue, review queue with approve/reject/refund and status filters.
   - [x] In-place plan switch (downgrade/upgrade) before expiry — `POST subscriptions/switch`
     creates a pending switch linked to the active subscription; approval supersedes the old plan
     (badge never drops), rejection keeps the current plan; `GET subscriptions/active` powers the UI.
8. **Notifications**
   - [x] Real-time + persisted; unread badge (server-computed), per-module.
   - Real-time via Reverb: `NotificationCreated` broadcast on `private-user.{id}` (realtime-notifications), persisted feed + unread-count + mark-all-read endpoints.
9. **Signature Feature — Group Story Threads** ✅
   - Selected direction: a 24-hour collaborative, multi-contributor timeline in a group.
   - [x] Backend: `threads` / `thread_entries` / `thread_reactions` tables, Domain service +
     repository, member-only endpoints (start/show/add-entry/reactions), `threads:expire`
     scheduler writes a recap (entries, participants, reaction totals, top contributor).
   - [x] Realtime via Reverb: `ThreadEntryAdded` + `ThreadReactionAdded` on `private-group.{id}`
     (6 reactions: like/love/haha/wow/sad/angry, toggle/switch).
   - [x] Frontend: Story thread button in group chat header → `GroupThreadModal`
     (composer + image attach + reaction bar + live Echo sync + ended recap view).
   - [x] Tests: 14 feature tests (auth, membership, idempotent start, expiry, reactions, recap,
     broadcast events).
10. **Discovery** (added during the Phase 1 polish pass)
    - [x] `GET /users/top` — reach-ordered accounts the viewer does not follow yet, so a new
      user has somewhere to follow from before they know anyone.
    - [x] `POST /users/match-contacts` — opt-in phone matching on the last 10 digits, capped at
      500 numbers, nothing stored, suspended/banned accounts and self excluded.
11. **Business & Professional Profiles** (added during the Phase 1 polish pass)
    - [x] `account_type` = `personal` / `professional` / `business`, set from `PATCH /me`, with
      `User::accountType()` as a read accessor so a freshly registered account is never a null
      enum.
    - [x] Publicly listed `contact_email` / `contact_phone` behind `show_contact`, editable only
      by the owner. The account's own `email` / `mobile` are never published.
    - [x] Profile actions follow the account type: `Follow` primary everywhere, `Message` outline
      for personal accounts and `Contact` outline for a business account with published contact.
      Empty contact cannot be toggled on by accident.
    - [x] Downgrading to `personal` wipes the contact columns and unsets the flag.
12. **Account Center & Family Center** (added during the Phase 1 polish pass)
    - [x] Self-service deactivate (reversible) vs admin suspend (final) as two separate states;
      `POST /auth/reactivate` re-checks the password and issues a token in the same call, and the
      sign-in form switches into a reactivation mode on `ACCOUNT_DEACTIVATED` instead of
      dead-ending.
    - [x] Password change that revokes every *other* session while keeping the caller signed in,
      plus a per-device sessions list and revoke-one / revoke-all-others.
    - [x] `GET /me/account/export` returns one portable JSON file (credentials excluded) and is
      fetched with the bearer token rather than navigated to, so it works at all.
    - [x] Account delete anonymises and soft-deletes: posts and messages other people already
      have stay intact, follow edges are cleared on both sides, and the family membership is
      removed (dissolving the family if the deleted account owned it).
    - [x] Privacy + notification preferences in `user_settings`, created lazily, with the legal
      key list derived from the model so the validator cannot drift from the schema.
    - [x] Family Center: `family_groups` + `family_members`, roles `guardian` / `adult` / `teen`,
      add-by-username, role changes, leave, remove, rename, dissolve. The owner is always a
      guardian and can never be removed; only the owner may grant guardianship; a teen cannot
      manage the roster. Someone else's family returns `404`, not `403`, so the endpoint cannot
      be used to discover a household. 23 feature tests.
    - [x] Settings page as a one-open-at-a-time accordion (Appearance, Account Center,
      Preferences, Security, Family Center, Help & about) so a person looking for Appearance does
      not page past the "Delete account" button.

**Phase 1 status: complete.** Everything above is shipped except four items that are
deliberately v2+ work, listed here so the deferral is a decision and not an omission:

| Deferred item | Why it is not v1 | Lands in |
| ------------- | ----------------- | -------- |
| Image resize + thumbnail variants | Uploads store the original; feeds serve it directly. Needs an image pipeline (Imagick/queue workers) and a storage policy before we re-encode user bytes. | `08-media-pipeline.md` v2 |
| Server-ranked feed ordering | v1 is a cursor feed (own posts + followed authors). Ranking needs a fan-out table and Redis that only pay off at scale. | Phase 3 |
| Server transcode + HLS + poster | v1 plays progressive uploads. Transcoding is a queue + storage cost before there is traffic to justify it. | `08-media-pipeline.md` v2 |
| Gallery wallpaper uploads + AI wallpapers | Needs a per-user wallpaper asset table, upload quota and moderation review before user-supplied bytes can become a chat background. | Phase 2 |

**Exit criteria:** Public beta. Anonymous visitor → sign-up → follow someone → post →
reel → chat in a group → buy blue tick → see notifications, all with no hangs.

---

## Phase 2 — Apps + AI

- **Mobile:** React Native clients (iOS + Android) hitting the same API.
- **AI Voice Assistant**
  - Voice messages transcribed (server-side) + AI summarization.
  - "Personal assistant": summarizes your groups, drafts replies, schedules posts.
  - Feeds into the signature feature if applicable.
- **Payments:** full gateway integration, digital receipts, refunds.
- **Push notifications** (FCM/APNs) via queue workers.

**Exit criteria:** App Store / Play Store listings with chat + feed + reels + AI.

---

## Phase 3 — Scale (microservices + DevOps at full depth)

Extract hot paths from the modular monolith into `services/` when load demands:

| Candidate service | Why extract | First extraction trigger |
| ----------------- | ----------- | ------------------------ |
| Chat (Reverb + Redis) | High write throughput, needs horizontal WS scaling | >5k concurrent WS connections |
| Media/Transcode | CPU-heavy, isolate failures | Transcode queue > 10k jobs/day |
| Feed fan-out | High read amplification | Feed p95 > 200ms |
| Notifications | Back-pressure isolation | Notifications queue backlog |

DevOps depth (phase 3):
- Kubernetes / managed container platform with autoscaling.
- Read replicas, Redis Cluster, queue workers autoscaled.
- Observability: structured logs, OpenTelemetry traces, dashboards, SLOs, on-call alerting.
- Blue/green or rolling deploys; every service deployable independently.

---

## Theme: "Always landable"

Every phase keeps the app **deployable and demoable**. If we can't demo at the end of a
week, we cut scope, not quality.

## After V1 — the company lever

- Blue tick becomes the identity layer of the platform (trust = paid).
- The signature feature + AI voice assistant become the moat competitors can't copy fast.