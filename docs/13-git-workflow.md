# 13 — Git Workflow

Monorepo, trunk-based with short-lived feature branches.

## Branches

| Branch | Purpose | Merge policy |
| ------ | ------- | ------------ |
| `main` | always deployable; staging auto-deploys | CI green + review |
| `feat/<slug>` | one feature per branch | squash-merge to main |
| `fix/<slug>` | bugfixes (also hotfix from main) | squash-merge |
| `perf/<slug>` | performance work (stress markers) | squash-merge |
| `docs/<slug>` | documentation | squash-merge |
| `release` → tag `vX.Y.Z` | prod releases | tag from main |

Short-lived: feature branches live < 3 days. Long-lived = bug.

## Conventional commits

```text
feat(chat): add unread counter event for group revalidation
fix(media): guard empty upload session in reels
perf(feed): eager-load profile cards in feed query
test(billing): add idempotency-key retry test
```

Commit subject ≤ 72 chars, imperative mood, scope `(chat|feed|reels|media|billing|api|frontend|docs|ci)`.

## Day-to-day loop

```powershell
git checkout -b feat/chat-unread-count   # from latest main
# …work, tests green…
git add -A; git commit -m "feat(chat): …"
git push -u origin feat/chat-unread-count
# open PR → CI runs → review → squash-merge to main
```

## Merge & release rules

- Squash-merge features (clean main history). Keep PR title = commit title.
- Branch protection on `main`: 1 approval + CI green. Skip review only for trivial docs/CI.
- Releases: `git fetch origin; git checkout main; git pull; git tag v1.0.0; git push origin v1.0.0`
  → prod deploy triggered by the tag.
- Hotfix: create `fix/x` from `main`, merge with CI, tag patch `vX.Y.Z+1`.

## Do / don't

- ✅ Commit often, small; each commit compiles + tests pass (if possible).
- ✅ Include stress marker tests in perf PRs.
- ❌ Don't commit `.env`, vendor, node_modules, build output, keys.
- ❌ Don't force-push shared branches. Rebase only your unpublished commits.
- ❌ Don't merge broken CI ("fix after merge" is a trap).

## Repo notes

- Remote: `git remote add origin https://github.com/azimkhan07/chatting_sysem.git`
- First push (empty repo): `git push -u origin main`; CI badge once `ci.yml` lands.