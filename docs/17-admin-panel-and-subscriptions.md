# Admin Panel & Subscriptions

> Status: PLANNED (skeleton in Phase 1, implementation Phase 2–3).

## Vision (user ask)

- An **admin panel** exists at `/admin` where the team manages the business: users,
  blue-tick subscriptions, payments and support.
- **AI chat support**: users reach support from inside the app via a chat widget;
  an AI assistant answers instantly, escalates to a human when needed. Admin sees
  transcripts.

## Admin panel

### Surface & auth

- Served at `/admin` as a **separate app surface** (own layout, own route tree). It is
  never rendered inside the user SPA shell.
- Auth: admin users live in the same `users` table but hold a role of `admin`/`super_admin`
  (roles table below). API endpoints under a dedicated Admin API namespace
  (`/api/v1/admin/*`) with a separate middleware that requires the admin role AND a
  token granted the `admin` capability. No user token can reach admin routes.
- Phase 3 hardening: mandatory MFA (TOTP) for admins, login from allow-listed IPs,
  audit log of admin actions.

### Features (by phase)

| Area | Phase 2 | Phase 3 |
| ---- | ------- | ------- |
| Users | List/search, view profile, ban, verify, assign role | — |
| Subscriptions | Plans CRUD, view subscribers, toggle blue tick | Manual adjustments, invoices PDF |
| Payments | Record transactions (Razorpay), statuses | Stripe/Razorpay webhooks, refunds |
| Support | View AI chat transcripts, reply, reassign | Ticket queues, SLAs, canned replies |
| Moderation | Content report triage, take-down | ML-assisted flagging |

## Subscriptions & blue tick (the core paid product)

- Blue tick = premium status, priced ₹1–₹5/month (current plan: flat ₹5/month, see
  18 for pricing ladder).
- Subscription lifecycle: `active → past_due → cancelled → expired`, driven by payment
  provider webhooks; expiry flips `is_verified=false` automatically (queued job).
- `plans` and `subscriptions` tables already sketched in `05-data-model.md`.

## Roles & permissions data

- `roles` (name, guard) and `role_user` pivot; a `super_admin` can grant `admin`.
- Enforcement: a `EnsureUserIsAdmin` middleware consulting roles via the same
  repository pattern as auth (`Domain\Admin` in Phase 2), NOT scattered `if`s.

## AI chat support

- Floating `SupportChat` widget ships in the app shell (Phase 1 placebAI: instant
  canned answers + "escalate to a human" that files a support ticket).
- Phase 2: the widget talks to the Laravel support API, which proxies an LLM (rules
  from the product KB) and stores transcripts (`support_threads`, `support_messages`).
- Escalation rules: user asks for human, detects PII/payment words, or LLM confidence
  low → ticket created, admin notified (queue + realtime).

## Naming & conventions

- Admin code goes under `backend/app/Domain/Admin/` and `frontend/src/admin/`
  (own tree, own `paths`), mirroring the Domain structure from Phase 1.
- Every admin action that mutates state is an Action + repository method with a
  corresponding feature test, per `12-coding-standards.md`.