# Security & Route Strategy

> Status: ACTIVE · Decides how pages are reached and how surfaces are defended.

## Threat model snapshot

| Asset | Threat | Primary defense |
| ----- | ------ | --------------- |
| User routes | Enumeration, guessing other users' pages/IDs | Opaque route tokens + non-guessable user handles/ID hashing |
| API | Credential stuffing, abuse | Sanctum tokens, `throttle:auth`, per-user rate limits |
| Admin | Privilege escalation, takeover | Separate `/admin` perimeter, roles, MFA (Phase 3) |
| Sessions | Token theft | HTTPS-only, short-lived tokens + refresh, revoke on logout |

## Route strategy (frontend, Phase 1)

We ship **three route spaces**:

1. **Public marketing** — the bare domain `/` and `/login`, `/register`.
2. **User app** — reachable ONLY while authenticated. Paths use **opaque route
   tokens** instead of descriptive names, so the page topology is not guessable
   from the URL (e.g. the feed is not `/feed`, chat is not `/chat`).
3. **Admin** — reserved under `/admin`, a completely separate surface and
   auth perimeter (see `17-admin-panel-and-subscriptions.md`).

### What "encrypted routes" means here (and what it does NOT)

- The SPA maps semantic keys → opaque tokens via `frontend/src/lib/paths.ts`
  (`path('chat')` returns a stable token path). Route definitions, links and
  redirects all go through this helper — a URL like `/a8f3c` means nothing to a
  casual observer.
- **This is obfuscation, not encryption.** A client-side SPA cannot hold a real
  secret. The actual security boundary is the **API**: every endpoint authenticates
  the token and re-checks authorization for the requested resource. Opaque routes
  reduce scanning, social engineering and link guessing; authorization on the API
  is what stops access.
- Resource *visibility* rules (who may view a profile) are enforced server-side in
  Phase 2. Never rely on "hard to guess" as the only control.

## API security baseline

- Only Sanctum bearer tokens; tokens are scoped (admin capability not grantable to
  user tokens) and revocable on logout.
- `throttle:auth` (10/min/IP) on auth endpoints; normal endpoints get their own rate
  limiters per route group.
- All input validated in `FormRequest`s server-side; display layer escapes output.
- HTTPS in every environment except local dev; `secure` cookies; `SameSite=Lax`.
- `X-Request-Id` echoed back in the envelope meta for tracing (see `06-api-design.md`).

## Rules for future work

1. Whenever a new user page is added, register a token in `paths.ts` and build the
   route via `path(...)`. Do NOT hardcode path strings in links.
2. Whenever a new admin capability is added, gate it server-side by role AND keep it
   off the user API entirely.
3. Secrets live only in server env files (`.env*`), never in the frontend bundle
   or committed config.
4. Add a test with each authorization-sensitive endpoint asserting 401/403/404 as
   documented in `06-api-design.md`.