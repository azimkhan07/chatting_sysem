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
   - Avatar/cover upload (media pipeline v1: images resize + thumbs).
2. **Profiles & Follow**
   - Profiles, bio, followers/following, follow/unfollow (server-computed counts).
3. **Feed (Home)**
   - Posts (text/image/video), server-ranked feed, cursor pagination, infinite scroll.
   - Like / unlike, comments (nested one level), share count.
4. **Reels**
   - Upload → server transcodes (rotations) + HLS + poster.
   - Full-screen vertical player, autoplay muted, like/comment/share.
5. **Explore**
   - Search users/hashtags; trending grid (Redis-ranked).
6. **Chat (1:1 + Groups)**
   - Reverb WebSockets: messages, typing, presence, read receipts.
   - Groups: create/join/invite, roles (owner/admin/member).
   - **Stress case:** a user with 100+ groups must have a snappy chat list (server-side
     last-message + unread aggregation via Redis/DB views).
7. **Blue Tick**
   - Request verification → pay ₹1/₹5 (UPI/Mock gateway v1) → admin review → badge.
   - Auto-renew + expiry. Cancellation removes tick.
8. **Notifications**
   - [x] Real-time + persisted; unread badge (server-computed), per-module.
   - Real-time via Reverb: `NotificationCreated` broadcast on `private-user.{id}` (realtime-notifications), persisted feed + unread-count + mark-all-read endpoints.
9. **Signature Feature**
   - Selected direction, built, tested, shipped (see `01-vision-and-features.md`).

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