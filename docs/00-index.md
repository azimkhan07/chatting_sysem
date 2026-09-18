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