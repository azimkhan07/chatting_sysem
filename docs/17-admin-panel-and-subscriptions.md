# Admin Panel & Subscriptions

> Status: ACTIVE (rebuild underway). Spec below is the full product ask — the admin
> console is being **rebuilt from scratch** as a standalone web app (new `admin/`
> frontend app + expanded `/api/v1/admin/*` backend). The old in-app `/admin`
> surface (dashboard + review queue) is being removed from the user app.

## Vision (user ask)

- A single **Admin + Support** console, used only on desktop/web (no admin or support
  login on mobile for now).
- Admin and Support share **one database** and one API. The app has its own DB in
  production, but the admin combines access to users + subscriptions + payments from
  it.
- Support staff have **their own role/id** (`support`) separate from admins. Every
  user query or complaint filed from the user-facing support page lands here; support
  replies inline or emails the user back.
- Support also **owns subscription activation**: payment → support verifies → plan
  goes active → blue tick shows + the bought features unlock.

## Surfaces & routing (production)

| Surface | Host | Notes |
| ------- | ---- | ----- |
| User app | `amtechat.com` | separate DB |
| Admin console | `admin.amtechat.com` | web only, dark polished template (Mantine) |
| Support console | `support.amtechat.com` | same app, `support` role route tree |

Vite build with base `/`, proxied in dev via same `127.0.0.1:8000` backend.

## Auth & session rules (hard requirement)

- Admin and support tokens are minted only for users holding `admin` / `super_admin`
  / `support` roles; the user app can never reach `/api/v1/admin/*`.
- **Single session**: logging in on a new device must notify (alert banner) that the
  session was taken over elsewhere, and revoke the previous one.
- **Max 3 concurrent devices** for both admin and support. A 4th login is refused
  until one existing session is revoked.
- Phased hardening: MFA (TOTP), allow-listed IPs, action audit log.

## Dashboard

- Cards: **total users**, **active subscriptions**, **suspended users**, plus a
  **logged-out/other** snapshot. Revenue + blue-tick conversion shown if present.
- **Range dropdown: Today / This Month / This Year / Custom**.
- **Custom** opens a calendar date picker pair (start → end).
  - **Future dates are never selectable** anywhere a calendar is used (this rule
    applies to every date calendar in the app, admin and user side). Past dates are
    always allowed. `maxDate = today` enforced at the picker level.
- All counts re-fetch when the range changes.

## Users tab

Not a bare list first. Layout, top → bottom:

1. **4 stat cards**: All users · Joined today · Joined this month · Joined this year.
2. **Filter bar**: suspicious users · suspended users · deactivated users · by country.
   - Country comes from registration (Phase 2: Google Maps geocode saves the
     user's country at signup). Column/filter exist now, data populates then.
3. **List columns**: username · name · email · country · status · joined date ·
   actions.
   - **Flagged / unflagged** column is **deferred to Phase 2** (the app does not
     implement flagging yet — the column is a stub until then).

## Subscriptions tab (admin)

- **Plan pricing form (country-wise)** per plan:
  - Select country → **currency + symbol auto-fill** (e.g. IN → ₹, US → $, AE → AED).
  - Per-country price input + per-plan period.
  - **Feature unlock checkboxes** — each feature is listed; the admin picks which
    features unlock with each plan.
- Plans at launch:
  - **Basic** (cheap, 2–3 features)
  - **Standard**
  - **Premium** (everything)
- The **user app gets a Subscriptions page** (own navigation entry) showing at least
  **three price cards** (Basic / Standard / Premium) with their offers.
- Every locked feature must be **visible as a named row in the price card list** —
  a "locked" feature must never be silently absent. The lock crown in-app points to
  the same `ChatFeature` keys the admin unlocks.

### Feature registration (shipped)

`ChatFeature` is the **single registration point**. Adding a case is the whole job —
there is no seed array to append to and the admin app needs no rebuild.

```php
// backend/app/Domain/Chat/Enums/ChatFeature.php
case VoiceNotes = 'voice_notes';
```

- The case's backing value is the **keyword** a plan stores in its `features` list.
- `label()` / `blurb()` hold the title shown on the admin checkbox and the one-liner
  shown on the in-app lock. Both fall back to a humanised keyword rather than
  throwing, so a half-finished registration shows an ugly label instead of bricking
  the boot.
- `tier()` returns `FeatureTier::Premium` or `FeatureTier::Free`. **Default is
  `free`.** A new case is not paid until somebody names it in the `match` on
  purpose — the expensive mistake is marking a live feature premium by accident and
  locking every existing subscriber out of it. Phase 2 flips specific cases to
  `Premium` when subscriptions go live.
- `FeatureCatalogueSync` materialises the enum into the `features` table on every
  boot, which is what the console's subscription form reads. It is idempotent, only
  writes rows that actually differ, and **deactivates rather than deletes** an
  unregistered keyword so plan JSON that still names it stays resolvable.
- `php artisan chat:features` lists every registration (keyword, title, tier) and
  `php artisan chat:features --dry-run` reports what would change without writing.

A plan may only name a keyword that is **live in the catalogue** — enforced by
`Rule::exists` on `SavePlanPricingRequest`. This is what stops a plan selling a
feature the app does not implement, which is how `calls` ended up on a plan. The
console strips retired keywords when it loads a row, so re-saving an older plan
still works.

`premium` features become checkboxes on the plan form; `free` ones are listed as
read-only "Free for everyone" because a box that unlocks nothing would read as
something you are buying.

## Support tab

- Inbox of user filed queries/complaints (`support_tickets`), with status
  (open / replied / closed), priority, and user context (username, country).
- Reply inline (creates a `support_messages` thread row) **and/or** send the user an
  email from a stored template.
- Support role handles subscription activation → activates → blue tick + feature
  unlock fire the same path as admin review today.

## Email system

- **Templates are CRUD**: title + subject + HTML body (+ plain-text preview).
  - Body edited in a small HTML-safe editor; a **text preview panel** shows how it
    reads before saving.
  - Sending = pick a title → subject (from template) + body go out to the user.
- **Email config CRUD** (host, port, username, password/API key, from address) — no
  redeploys to change transport.

## Payment gateway config

- Backend code is written **once** against a generic gateway contract; all gateway
  credentials are config CRUD: **key · merchant id · secret · endpoint** (+ currency,
  enabled). Buying a new gateway = adding its credentials in admin, not touching code.
- Payments recorded against subscriptions; support verifies → activates.

## Data model additions (backend)

- `roles` / `role_user` (admin, super_admin, support) — reused from auth docs.
- `admin_sessions` or capability tokens with device_id + limit-to-3 enforcement.
- `plan_countries` (plan_id, country, currency, currency_symbol, price, period).
- `plan_features` / pivot `plan_feature` (feature key ⇄ plan).
- `support_tickets`, `support_messages`.
- `email_templates` (title, subject, html_body, text_body).
- `email_config` (single row CRUD).
- `payment_gateways` (name, key, merchant_id, secret, endpoint, currency, enabled).
- `user_country` on `users` (Phase 2 geocode; filter now).

## Naming & conventions

- Frontend: `admin/` at repo root — own Vite app, own `src/pages/{dashboard,users,
  subscriptions,support,email,gateways}`, Mantine UI, web-only.
- Backend: `backend/app/Domain/Admin/`, `Domain/Support/`, `Domain/Plans/`,
  `Domain/Email/`; every mutating admin action is an Action + repository method with
  feature tests (12-coding-standards.md).