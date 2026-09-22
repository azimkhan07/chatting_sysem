# docker/ — Infrastructure & Local Dev Stack

This folder holds everything that runs the app in containers (see docs/11).
The canonical dev compose file lives here; `docker-compose.yml` at the repo
root is only a forwarder so plain `docker compose up` keeps working.

## Layout

```text
docker/
├─ compose.yaml            # canonical dev stack: api(backend) + queue + scheduler + redis + mysql
├─ compose.prod.yaml       # prod-ish overlay (migrations, fpm, scaled queue) — template for staging/prod
├─ redis/redis.conf        # persistence + maxmemory policy (mounted by redis service)
├─ nginx/api.conf          # nginx site for php-fpm deployments (prod-ish)
└─ README.md
```

## Local one-liner (dev)

```powershell
# from repo root
docker compose -f docker/compose.yaml up -d --build
docker compose -f docker/compose.yaml exec backend php artisan migrate --seed --class=DemoSeeder
# API → http://localhost:8000 · frontend → npm run dev (http://localhost:5173)
```

Plain `docker compose up -d --build` from the root also works (forwarder).

## Verify health

```powershell
docker compose -f docker/compose.yaml ps
# mysql → healthy · redis → healthy · backend/queue/scheduler → Up
```

## Phase 1 note

Reverb (WebSocket) container arrives with Phase 1 chat. The compose file is
deliberately minimal until the backend ships Reverb config + channels.