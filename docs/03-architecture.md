# 03 — System Architecture

## High-level view (v1)

```text
                        ┌────────────────────────────┐
                        │        Web Client          │
                        │  React 19 + TS (SPA/PWA)   │
                        └──────────────┬─────────────┘
                                       │ HTTPS (JSON API + WS)
                        ┌──────────────▼─────────────┐
                        │      API Gateway / Nginx   │
                        │   (limits, TLS, static)    │
                        └───────┬────────────┬───────┘
                                │            │
                  ┌─────────────▼──┐      ┌──▼─────────────┐
                  │  Laravel 12    │      │  Reverb (WS)   │
                  │  Core (API)    │      │  presence/dm   │
                  └──┬─────┬───┬───┘      └───┬───────────┘
                     │     │   │              │
        ┌────────────▼─┐ ┌─▼───────▼──┐   ┌───▼──────────┐
        │ PostgreSQL   │ │  Redis     │   │  Queue worker│
        │  (source of  │ │  cache +   │   │  (Redis)     │
        │   truth)     │ │  queues    │   │  media/feed/ │
        │              │ │  presence  │   │  notify      │
        └──────────────┘ └────────────┘   └──────────────┘
```

- **Source of truth:** PostgreSQL (dev fallback: SQLite). One database in v1.
- **Redis** is mandatory, not optional. Used for: cache, queues, rate limits, presence,
  feed ranking counters, unread aggregation.
- **Reverb** serves realtime (chat, presence, notifications) and is horizontally scalable.
- Everything clients need is computed **in the API**, serialized once, sent ready-to-render.

## Microservices mindset (v1 → v3)

v1 ships as a **modular monolith**:

- Domain modules live under `backend/app/Domain/<Module>/` with **enforced boundaries**
  (`actions`, `contracts`). No cross-module calls except via contracts.
- This makes extraction to `services/` mechanical later (ADR-001).

Suspects for v3 extraction (see roadmap phase 3): chat, media/transcode, feed fan-out,
notifications.

## Tech stack decisions

| Area        | Choice                   | Notes |
| ----------- | ------------------------ | ----- |
| Language    | PHP 8.2+ / TypeScript    | Backend / frontend |
| Framework   | Laravel 12              | LTS-grade, huge ecosystem |
| Frontend    | React 19 + Vite 8 + TS  | SPA; PWA-ready |
| DB          | PostgreSQL (prod)        | SQLite in dev, MySQL also fine |
| Cache/Queue | Redis                    | Cache + broker for queues + presence |
| Realtime    | Laravel Reverb           | First-party WebSocket server |
| Auth        | Laravel Sanctum          | Bearer tokens (SPA + apps) |
| Payments    | UPI gateway / Stripe (v2)| v1: mock gateway behind a port/contract |
| Mobile      | React Native (v2)        | Same API |
| CI/CD       | GitHub Actions           | See `11-devops-cicd.md` |
| Infra       | Docker, then K8s (v3)    | Compose locally |

## Repo layout (monorepo)

```text
amteCHAT/
├─ backend/              # Laravel 12 — the core API
│  ├─ app/
│  │  ├─ Domain/         # business modules (bounded contexts)
│  │  ├─ Http/Controllers/ # thin HTTP layer
│  │  ├─ Http/Resources/   # API resources (projections)
│  │  └─ Services/       # shared services (Media, Redis, etc.)
│  ├─ database/migrations + seeders
│  ├─ routes/            # api.php v1
│  ├─ tests/
│  └─ .env.example
├─ frontend/             # React SPA
├─ services/             # v3 microservices live here
├─ docker/               # compose files, Dockerfiles, nginx, ci
├─ docs/                 # this documentation
└─ .github/workflows/    # CI/CD pipelines
```

## Environment matrix

| Env       | Where              | DB        | Redis | Notes |
| --------- | ------------------ | --------- | ----- | ----- |
| local     | dev machine        | SQLite/MySQL | Docker | `php artisan serve` + `vite` |
| testing   | GitHub Actions     | SQLite in-memory | `MemoryTransport` | Fast, no external deps |
| staging   | Preview deploy     | Postgres | Redis | Verifies CI artifact |
| production| Managed host       | Postgres | Redis | Read replicas added v3 |

## Failure handling in v1

- Queues retry with backoff; failed jobs land in a dead-letter queue (observable).
- Realtime is a **delivery accelerator**, not a guarantee: the DB is always the source of
  truth; a reconnect re-syncs state (messages, unread, presence) via the API.
- Cache misses must never break correctness — only add a rebound DB query.
- Rate limiting (Redis buckets) on auth, feed, search, chat send.

## Security posture

- HTTPS everywhere; secrets in env/git-secrets, never in code.
- Sanctum tokens scoped; sensitive endpoints require fresh password/2FA (later).
- Upload validation server-side (magic bytes, MIME whitelist, size limits, scan hooks).
- Admin actions audited (verified users, billing, content moderation).