# Business Strategy

> Status: ACTIVE · The "why" behind the roadmap; revisit quarterly.

## One-line strategy

Build an India-first social hub where the **blue tick is an affordable paid status**
(₹1–₹5/month) sold through a self-serve flow, while the core platform stays free —
so adoption grows because sharing is free, and revenue grows because status is
cheap and status-seeking is universal.

## Monetization ladder

| Tier | Price | What it buys | Status |
| ---- | ----- | ------------ | ------ |
| Free | ₹0 | Core: posts, chat, groups, profile | Phase 2 |
| Blue tick | ₹5/month (launch); discounts to ₹1 at scale | Verified badge, priority support, exclusive themes | Phase 2 |
| Premium creator | Later | Insights, custom branding, longer media | Phase 3 |
| Ads | Later & optional | Non-intrusive in-feed ads only after product-market fit | Phase 3+ |

Principle: **charging early, cheaply, and visibly beats free-forever.** It filters
willing payers, funds infra and keeps the team focused on delighting the paying
minority without degrading the free majority.

## Growth loops

1. **Invite loop** — every new user gets a share handle ("Join my circle on
   amteCHAT"); inviting N friends unlocks a theme (not the blue tick).
2. **Content loop** — blue-tick users surface slightly higher in discovery until
   they compete with real popularity; verified voices seed content quality.
3. **Chat retention** — streak badges + unread summaries keep habit; presence keeps
   users "accidentally" returning.

## North-star & KPIs

- **North star:** weekly active conversations (chat + group check-ins).
- Activation: registers → follows 3 → chats 1 (target < 10 min).
- Paid funnel: sees a verified user with blue tick → opens pricing → converts.
  Target conversion ≥ 3% of actives.
- Guardrails: spam reports / 1k MAU, p95 load time < 400ms on API (per no-hang doc).

## Phased go-to-market

| Phase | Focus | Exit criteria |
| ----- | ----- | ------------- |
| 1 (current) | Auth + design + secure routes | Login works, design loved, CI green |
| 2 | Core social (posts, follows, discovery) | 100 active users in invite beta |
| 3 | Billing (blue tick live), AI support | First paying user region, refunds < 1% |
| 4 | Mobile apps + AI voice assistant | App-store launches, blue tick on mobile |
| 5 | Monetization experiments, scale | Ads A/B, creator program, microservices split |

## Cost & unit economics (rough, revisit quarterly)

- Per active user: infra ~₹0.15/mo at 10k MAU (Redis + PHP + object storage).
- Blue-tick margin: ~85% gross (payment gateway ~2%, infra ~13%).
- Break-even ≈ 5–8k paying subscribers ≈ 200–300k free MAU. Feasible within 12
  months if invite loop lands.

## Rules we stick to

1. Never hide core features behind paywalls; sell *status and convenience*.
2. Every monetization experiment ships with a KPI dashboard (admin panel).
3. No ads until Phase 3 and never autoplay-audio ads.
4. Feature-defer anything that doesn't serve activation, retention or paid conversion.