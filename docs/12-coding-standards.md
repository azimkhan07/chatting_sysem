# 12 — Coding Standards (senior level)

Rules that keep the codebase fast to change and safe to run. Enforced by CI.

## PHP (backend)

**Style & lint**
- `laravel/pint` (PSR-12 + Laravel preset) — `composer pint`. CI fails on violations.
- PHPStan level 5 minimum (goal 6) — `vendor/bin/phpstan analyse --memory-limit=1G`.
  `strict_types=1` everywhere (`declare(strict_types=1);`).

**Structure**
- Controllers thin; domain logic in `Domain/*/Actions`. No Eloquent in controllers.
- Cross-module access only via `Domain/*/Contracts`. No magic strings for module names.
- Resource classes are the only serialization (no `$model->toArray()` leaks).
- Type everything: return types, param types, PHPDoc for array shapes.

**Data access**
- Eager load with `with()`; `select()` exact columns; `PreventLazyLoading` in prod.
- Never `N+1`; never `ORDER BY RAND()`; never raw user text into queries (parameterized).
- Money integers (paisa), dates in UTC, IDs = ULIDs.

**Transactions & idempotency**
- Multi-write business ops in DB transactions (`DB::transaction`, retry on deadlock).
- Every create/act endpoint accepts `Idempotency-Key`.
- Queued jobs idempotent + `ShouldBeUnique` where repeats are harmful.

**Tests**
- Action = Feature test (route→response+state) + unit test where pure.
- Naming: `test_creating_group_requires_at_least_two_members`.
- Backfill tests for every bug. Coverage wall on critical paths (billing, chat read).

**Naming & commits**
- Natural language camelCase; booleans `is_`, `has_`. Controllers plural nouns.
- Conventional Commits: `feat:`, `fix:`, `refactor:`, `test:`, `chore:`, `docs:`,
  `perf:`, `ci:` — imperative, ≤72 chars.

## TypeScript / React (frontend)

- TS `strict: true`; `noUncheckedIndexedAccess`; shared types generated from OpenAPI,
  drift guarded in CI.
- Components: function + hooks; no class components. Props typed via `interface`.
- Hooks in `hooks/`, feature hooks in `features/<x>/`. No prop-drilling >2 levels.
- Server state only via TanStack Query hooks (defined per feature in `queries.ts`).
- Animations: Framer Motion for entrance/motion; CSS for static taste. Respect
  reduced-motion. GPU-only props (`transform/opacity`).
- Async: functions `async` with typed `Result<T>`; no unhandled promise floating.
- No `any` (except for type-asserted third-party boundaries with comment).
- Imports sorted; no unused imports — `oxlint` errors in CI.
- Fetch via typed client only (no ad-hoc `fetch` in components).

## General

- **No comments that restate code.** Comment *why*, not *what*. Intent comments welcome.
- Never commit generated files, `.env`, keys, tokens. `.gitignore` honored.
- Feature work = branch + PR + green CI + review. Fixes can be direct on feature branches.
- Pair conventions: when in doubt, ask the docs (this folder) — they outrank habit.