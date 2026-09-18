# 11 — DevOps & CI/CD

From day zero: **push → CI → deploy → observe**. Everything in GitHub Actions.

## GitHub repository

- URL (working): `https://github.com/azimkhan07/chatting_sysem`
- After the repo exists: `git remote add origin https://github.com/azimkhan07/chatting_sysem.git`
- Branch protection on `main`: require CI green + 1 review before merge (set once team > 1).
- Secrets in GitHub → Settings → Secrets (never in code): `APP_KEY`, `DB_*`, `REDIS_*`,
  `STORAGE_*`, deploy SSH keys / provider tokens.

## Workflows (`.github/workflows/`)

| Workflow          | Trigger            | Steps |
| ----------------- | ------------------ | ----- |
| `ci.yml`          | push + PR on all branches | backend: composer update lock, Pint, PHPStan, PHPUnit (in-memory sqlite), stress marker; frontend: install, `oxlint`, `tsc`, build; artifact |
| `deploy-staging.yml` | push to `main`  | build images, migrate, run demo seed, smoke test both apps, comment status on PR |
| `deploy-prod.yml` | tag `vX.Y.Z`      | build + push images, zero-downtime migrate (deploy-time), health gates |
| `nightly.yml`     | schedule           | full `@group stress` + dependency audit (`composer audit`, `npm audit`) |

Shortcuts in dev: `ci.yml` path-filters — backend-only change skips frontend jobs.

## Environment & service matrix

| Env       | DB           | Redis     | Queue   | Realtime  |
| --------- | ------------ | --------- | ------- | --------- |
| local     | SQLite/MySQL | Docker `redis:7` | sync (local) | Docker reverb |
| CI        | in-memory    | `MemoryTransport` | sync    | n/a |
| staging   | Postgres     | Redis     | workers | reverb |
| prod      | Postgres+replicas | Redis | scaled workers | reverb cluster (v3) |

### docker/ layout (v1)

```text
docker/
├─ compose.yaml                 # dev: api, db, redis, reverb
├─ compose.prod.yaml            # scaled prod-ish stack override
├─ api/Dockerfile               # php8.3-cli + extensions + ffmpeg, nginx fpm
├─ reverb/Dockerfile            # node or php ws server container
├─ nginx/api.conf               # TLS terminate, static, proxy timing
└─ redis/redis.conf             # persistence + maxmemory
```

Local one-liner (dev):
```powershell
# root
docker compose -f docker/compose.yaml up        # db + redis + reverb
cd backend; composer install; php artisan migrate --seed --class=DemoSeeder
php artisan serve                                   # api
cd frontend; npm run dev                            # spa
```

## Deployment philosophy

- **Staging** = deploy of `main`. **Prod** = deploy of `vX.Y.Z` tags only.
- Migrations run **before** code swap (expand → migrate → contract → shrink).
- Health gates after deploy: `/health` returns db/redis/queue status; failure → rollback.
- State is external (DB/Redis/object store): containers are cattle.
- Backups: nightly Postgres dump + point-in-time WAL (v2); restore tested monthly.

## Observability (v1 baseline → v2 full)

- Structured logs aggregated (provider TBD: Loki + Grafana or managed).
- Metrics: RED (rate/errors/duration) posters per route; queue depth; Redis hit-rate;
  N+1 counter (Laravel `preventLazyLoading` + log). Alerts: 500-rate, queue backlog,
  DB connections, disk, memory.
- Trace request_id through logs; OpenTelemetry traces v3.

## Cost guardrails (bootstrapper project)

- Dev/staging on the cheapest solid tier; observability = open-source choices (or managed free tier).
- Object-store lifecycle rules: delete expired/trash media, thumbnails regenerable.
- Reels pipeline batches transcode into off-peak hours where pricing allows.

## Getting to CI green today (Phase 0 exit)

1. Create repo + `git remote add origin`.
2. Add `ci.yml` from `docs/11-devops-cicd.md` template.
3. Push → watch pipeline: Pint → PHPStan (level 5+ gradually) → PHPUnit → oxlint/tsc/build.
4. Add badge to README. Done = "first push is green".