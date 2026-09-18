# amteCHAT

A next-generation social platform — chat, feed, and connections — built as a scalable
monorepo. Working name: **amteCHAT** (final brand name to be decided at launch).

> Dream project. Built for a real launch, not a demo.

## Vision

- Web-first social platform (Instagram / Facebook / WhatsApp class)
- Own mobile apps after the web MVP
- **AI voice assistant** in the next major version
- **One signature feature** that Facebook, Instagram, X (Twitter) and WhatsApp do not have
- **Blue tick (verified badge)** made affordable — ₹1–₹5 / month — so the public actually
  stays engaged, with service quality scaled to the plan

## Tech Stack

| Layer            | Choice                                  |
| ---------------- | --------------------------------------- |
| Backend API      | Laravel 12 (PHP 8.2+)                   |
| Frontend (Web)   | React 19 + TypeScript (Vite)            |
| Database (local) | SQLite (dev) → PostgreSQL/MySQL (prod)  |
| Realtime / Chat  | TBD (Laravel Reverb / WebSockets)       |
| Mobile apps      | TBD (React Native)                      |
| Microservices    | Planned (phase 2)                       |
| DevOps           | Planned — Docker, CI/CD, monitoring     |

## Repository Layout

```text
amteCHAT/
├─ backend/            # Laravel API (the core domain)
├─ frontend/           # React + TypeScript web client
├─ services/           # Future microservices (empty for now)
├─ docker/             # Future Docker / infra (empty for now)
├─ docs/               # Architecture & product docs
└─ README.md
```

The core Laravel app holds the real domain model (users, auth, feed, chat, verification,
billing). As load grows, high-traffic pieces (chat, notifications, media, feed fan-out)
get extracted into `services/` as independent microservices.

## Docs

**Read these before coding.** They are the blueprint of the whole project.

Start at [`docs/00-index.md`](docs/00-index.md) — it links everything:

- Vision & features (blue tick ₹1–₹5, signature feature) — `docs/01-vision-and-features.md`
- Roadmap (v1 web → v2 apps+AI → v3 scale) — `docs/02-roadmap.md`
- Architecture & Laravel backend (eager loading, Redis, queues) — `docs/03/04`
- Data model & API design — `docs/05`, `docs/06`
- Realtime chat & media pipeline — `docs/07`, `docs/08`
- Frontend (React, animations) — `docs/09`
- Performance/caching (the "no hang" guarantee) — `docs/10`
- DevOps & CI/CD — `docs/11`
- Coding standards & git workflow — `docs/12`, `docs/13`

## Getting Started (Local)

Requirements: PHP 8.2+, Composer 2, Node 18+, npm.

### 1. Backend (Laravel)

```powershell
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

API will be at `http://127.0.0.1:8000`.

### 2. Frontend (React)

```powershell
cd frontend
npm install
npm run dev
```

Web app will be at `http://localhost:5173`.

## Roadmap

V1 → V2 → V3 phasing is documented in [`docs/02-roadmap.md`](docs/02-roadmap.md).
Short version: **v1** web MVP (feed, reels, explore, chat, blue tick ₹1–₹5, signature
feature) → **v2** mobile apps + AI voice assistant → **v3** microservices + full DevOps.

## Local Setup Notes (Windows)

- The Laravel dependency `vlucas/phpdotenv` ships a fixture named `nul.env`, which is a
  reserved filename on Windows. Git checkout fails unless NTFS protection is relaxed:

  ```powershell
  git config --global core.protectNTFS false
  ```

- PHP's `zip` extension is disabled in the XAMPP CLI build, so Composer falls back to git
  clones for some packages. Enabling `extension=zip` in `php.ini` speeds up installs.
