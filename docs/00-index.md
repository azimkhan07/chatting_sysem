# Project Documentation — Index

Working name: **amteCHAT** · Final brand name TBD at launch.

This is the single source of truth for the project. Follow these docs before writing
any feature. If something is not documented here, decide → document → implement.

## Map

| # | Doc | Purpose |
| - | --- | ------- |
| 01 | [Vision & Features](01-vision-and-features.md) | Product vision, feature set, blue tick, signature feature |
| 02 | [Roadmap](02-roadmap.md) | Phased plan: v1 web MVP → v2 apps + AI → v3 scale |
| 03 | [System Architecture](03-architecture.md) | High-level: Laravel core → future microservices, tech stack |
| 04 | [Backend Architecture](04-backend-architecture.md) | Laravel structure, modules, services, eager loading, Redis, queues |
| 05 | [Data Model](05-data-model.md) | Full domain schema (users, posts, reels, groups, chat, billing) |
| 06 | [API Design](06-api-design.md) | REST rules, response envelope, pagination, errors, auth |
| 07 | [Realtime Chat](07-realtime-chat.md) | WebSockets (Reverb), presence, unread strategy |
| 08 | [Media Pipeline](08-media-pipeline.md) | Post/reel upload → processing → delivery (HLS/thumbs) |
| 09 | [Frontend Architecture](09-frontend-architecture.md) | React structure, state, animations, UI direction |
| 10 | [Performance & Caching](10-performance-and-caching.md) | Eager loading, Redis, queues, the no-hang guarantee |
| 11 | [DevOps & CI/CD](11-devops-cicd.md) | GitHub Actions, Docker, environments, deployment |
| 12 | [Coding Standards](12-coding-standards.md) | Senior-level rules: typing, tests, style, code review |
| 13 | [Git Workflow](13-git-workflow.md) | Branching, commits, releases |
| 15 | [UI Design System](15-ui-design-system.md) | "Midnight Bloom" theme, tokens, primitives, motion |
| 16 | [Security & Route Strategy](16-security-and-route-strategy.md) | Encrypted/opaque routes, auth, admin perimeter |
| 17 | [Admin Panel & Subscriptions](17-admin-panel-and-subscriptions.md) | Admin app, roles, subscription/billing, AI support |
| 18 | [Business Strategy](18-business-strategy.md) | Monetization, growth, KPIs, phased roadmap |

## Non-negotiable principles

1. **The backend computes everything.** The client renders; it never derives business
   state (counts, unread totals, feed ranking, permissions). API responses are ready to
   render.
2. **No hang, ever.** Heavy workloads (many groups, large chats, big feeds) are handled by
   eager loading, pagination, Redis and queues — never by blocking a request.
3. **Senior-level quality.** Typed APIs, tests on every feature, code-style enforced,
   reviewed code, documented decisions.
4. **Scale-ready.** The Laravel core is a modular monolith; hot paths can be extracted to
   microservices (`services/`) without rewriting.

## Decision record (ADR)

Major decisions get recorded here so we never forget *why*.

| ADR | Decision | Reason | Date |
| --- | -------- | ------ | ---- |
| ADR-001 | Modular monolith (Laravel) as v1 core | Fastest path to MVP, still clean boundaries for future microservices | 2026-09-17 |
| ADR-002 | Server-rendered JSON API, React SPA | Backend owns all logic; consistent with "backend computes everything" | 2026-09-17 |
| ADR-003 | Redis required (cache + queues + presence) | Single well-understood infra primitive for performance | 2026-09-17 |
| ADR-004 | Reverb + WebSockets for chat | Native Laravel, first-party, Laravel-ecosystem-aligned | 2026-09-17 |
| ADR-005 | Opaque route tokens for user pages | Shrinks attack surface by hiding page topology; real gates stay on the API (see 16) | 2026-09-18 |
| ADR-006 | Admin is a separate `/admin` surface | Independent auth perimeter + roles; never ships inside the user SPA shell | 2026-09-18 |
| ADR-007 | Chat message reactions + delete ship as part of Phase 1 chat | Natural messaging primitives users expect; one small table, pure DB aggregates, Reverb events | 2026-09-23 |
| ADR-008 | Engagement metrics (like/comment/share) drive a Redis-ranked trending feed | One sorted set + scheduled rebuild; DB-ranked fallback keeps trending available without Redis | 2026-09-23 |
| ADR-009 | Chat inbox aggregates live in Redis hashes with DB fallback | 100+ groups inbox + unread badge never scan the full message table; source of truth stays Postgres | 2026-09-23 |

## Progress tracker

Latest status of the active build. Updated whenever a task finishes. All checks below
are green on the machine that built them: `phpunit` (166), `pint`, `phpstan` (level 5),
`tsc -b`, `vite build`, `oxlint` (warnings only, none blocking).

### Completed

| Task | Notes | Verified |
| ---- | ----- | -------- |
| Chat: message reactions (toggle/switch/remove) | `conversation_message_reactions` table, 6 emoji enum, `ChatReactionController`, aggregate counts in `MessageResource`, realtime `MessageReactionChanged` | phpunit + phpstan + build ✅ |
| Chat: delete message for everyone | Sender or group owner/admin; realtime `MessageDeleted`; optimistic UI removal | phpunit + phpstan + build ✅ |
| Chat: full-page layout (sidebar + full-body thread) | Inbox rail fixed width, thread fills remaining body on desktop; mobile = bottom tab bar + full-screen thread + back nav | build ✅ |
| Chat: mobile responsiveness pass | Back button, hidden inbox rail when a thread is open, bottom nav padding accounted for composer | build ✅ |
| Shared reactions module | `ReactionName`, `REACTIONS`, `REACTION_EMOJI`, `emptyReactions` in `frontend/src/lib/reactions.ts` reused by thread + chat | build ✅ |
| Full-page mid-section layout | AppShell content column fills the page width right after the sidebar; readable caps added to feed/form-heavy pages (Notifications, HashtagPage) | build ✅ |
| Chat: 100+ groups inbox aggregation | `ChatInboxCache` (Redis): per-user unread hash + last-message snapshots, single-pass grouped SQL fill, warmed on read; `unreadTotal` is cache-first | phpunit + phpstan + build ✅ |
| Posts: share count + share button | `post_shares` table (unique post+user, idempotent), `POST posts/{post}/share`, `shares_count` in `PostResource`, PostCard share with optimistic count + copy/native-share | phpunit/6 tests + phpstan + build ✅ |
| Posts: Explore trending grid | Redis sorted set `posts:trending` (like=1, comment=2, share=4), bump on actions, `posts:refresh-trending` every 5 min, DB-ranked fallback, `GET posts/trending`, Explore tab | phpunit + phpstan + build ✅ |
| Blue tick: cancel revokes badge | Cancelling the last active subscription immediately clears `is_verified`; survives when an active sibling exists; auto-renew/expiry tests | phpunit/6 tests + phpstan ✅ |
| Admin subscription review panel | Separate `/admin` surface: own login + token store, dashboard with per-status counts + net revenue, review queue with approve/reject/refund + status filters + pagination | phpunit/6 tests + phpstan + build ✅ |
| Admin review paid notification | `NotificationType::AdminReview` + `AdminReviewNotifier` fired from `SubscriptionService::recordPayment`, realtime to admins on their private channel | phpunit + phpstan + build ✅ |
| Chat: typing indicator | Server-throttled `POST conversations/{id}/typing` (existing) + Reverb `UserTyping` event; thread header shows "X is typing…" (group: "N people…") with 3.5s expiry, clears when the message lands | phpunit + phpstan + build ✅ |
| Chat: read receipts as DP (Instagram/Messenger style) | `MessageResource::read_by` = readers from member watermarks (`last_read_message_id`); UI draws reader DPs (up to 3 + overflow, overlap strip) on the last read message, replacing the ✓✓ double tick | phpunit + phpstan + build ✅ |
| Chat: full-height thread card + compact new-message button | Chat card fills the whole page height (hides page background, even when empty); "New message" `w-auto` so it no longer stretches full width | build ✅ |
| Full-viewport shell with svh fallback | `app-shell` class: `100vh` fallback + `100svh` override so the app frame never collapses when `svh` is unsupported | build ✅ |
| Subscription switch plan (downgrade/upgrade) in place | `POST subscriptions/switch` + `GET subscriptions/active`; pending switch links old active subscription (`switch_from_subscription_id`); approval supersedes the old plan and keeps the badge continuous; rejection leaves the current plan untouched; admin review shows "Plan switch from…" | phpunit/8 tests + phpstan + build ✅ |
| Light theme: pure-white canvas | Light mode canvas unified to `#ffffff` (was `#f2f4fb`) so the page reads as one full white surface; dark theme unchanged | build ✅ |
| Password show/hide toggle | Eye toggle on every password field: Login, Register (both), ResetPassword, Admin console — via shared `AuthField` + exported `EyeIcon`/`EyeOffIcon` | build ✅ |

### Pending / next

| Item | Notes |
| ---- | ----- |
| Group invite flows hardening (member add via invite already live API-side) | Join UX polish |
| Chat presence (online status indicator) | Roadmap item 6 leftover; typing + read receipts shipped, presence still open |
| Full suite re-run against real MySQL + Reverb in Docker | CI covers it via GitHub Actions |

See [02 — Roadmap](02-roadmap.md) for the phased plan; ticked items are shipped plus tests.