# 09 — Frontend Architecture

SPA (React 19 + TypeScript + Vite 8). Renders server-projected data; owns *presentation*
and *animation* — never business logic.

## Stack

| Concern       | Choice |
| ------------- | ------ |
| UI            | React 19 (function components + hooks) |
| Language      | TypeScript (strict) |
| Build         | Vite 8 |
| Routing       | React Router (data router) |
| State         | Server state → TanStack Query; local UI state → zustand |
| Forms         | React Hook Form + zod |
| Styling       | Tailwind CSS v4 (design tokens) + CSS modules for microunds |
| Animation     | Framer Motion (+ custom canvas for liquid effects), reduced-motion honored |
| HTTP          | fetch wrapper (typed client generated from OpenAPI) |
| Realtime      | laravel-echo + Reverb |
| Reels player  | HLS.js on <video> with custom UI |
| PWA           | vite-plugin-pwa (v1 later) |

## Folder layout

```text
frontend/src/
├─ app/            # router, root providers, error boundaries, shell layout
├─ pages/          # route components (Login, Explore, ChatWindow, Feed, Reels…)
├─ features/       # per-feature UI modules (feed, chat, reels, profile, billing, search)
│  ├─ chat/
│  │  ├─ components/   # ConversationList, MessageBubble, Composer, Typing…
│  │  ├─ queries.ts    # TanStack Query hooks (fetch/update)
│  │  ├─ useWebSocket.ts
│  │  └─ index.ts
│  └─ reels/ ...
├─ api/            # typed client, endpoints, auth interceptor
├─ lib/            # utils, design tokens, animation presets, format helpers
├─ hooks/          # shared hooks (useCursorPagination, useInfiniteFeed, useOnline)
├─ components/ui/  # primitives (Button, Avatar, Badge, Sheet, Skeleton, Toast)
└─ styles/
```

## State rules

1. **Server state lives in TanStack Query** — caches projections keyed by url+cursor;
   invalidation on `subscribe`/`mutate` via actions. Stale-while-revalidate.
2. **Local UI state only** (composer text, open sheet) → zustand.
3. Never compute counts/percentile/unread client-side from lists. The API says it.
4. Optimistic updates ONLY for idempotent, cheap actions (like/unlike); rollback + server
   reconcile. Never optimistic on billing/payments.

## Data fetching

- `useCursorPagination('feed')` shared hook: fetches page, holds `nextCursor`,
  `hasMore`, dedupes by cursor, prepends optimistic items, merges WS updates
  (avoid double-counting via `lastAck`).
- Feed infinite scroll via IntersectionObserver sentinel (debounced).
- Query keys:
  - `['feed', cursorKey]`
  - `['conversation', convId, 'messages', afterId]`
  - `['unread', 'me']` → returns server total, WS events bump it via `setQueryData`.
- Pending states: skeletons (layout-matched), never layout shift.

## Realtime client

- `laravel-echo` connects to Reverb; channels map to `docs/07-realtime-chat.md`.
- `useChannel('private-group.'+id)` returns last event; component decides insert.
- On reconnect: invalidate `['conversations']`, `['unread','me']` and re-cursor messages.

## Animation design language

- **Entrance:** route transitions = 220ms ease-out slide/fade (screen swipes), stagger
  feed items by 40ms, scale-burst on elements with chaotic paths (like hearts).
- **Micro-interactions:** 80–150ms quick springs (Framer Motion `spring`), respects
  `prefers-reduced-motion`.
- **GPU-friendly only:** animate `transform` + `opacity`; never `layout` props mid-frame.
- **Performance:** virtualized lists (react-window/`@tanstack/virtual`) for chat
  messages & explorer grid; `will-change` scoped; IntersectionObserver replaces
  scroll/throttle listeners.
- Love the "different" ask: custom marquee/scrolling ambient elements, glass blur
  surfaces, magnetic hover states — tasteful, not gimmicky.

## Performance budgets (web)

| Metric      | Target             |
| ----------- | ------------------ |
| LCP         | < 2.0s (median)    |
| CLS         | < 0.05             |
| INP         | < 200ms            |
| Chat render | < 16ms per frame on 10k-message scroll |
| Feed scroll | 60fps virtualized  |
| JS gzip     | < 180kB initial route (code-split per route) |

## Accessibility

- Reduced-motion toggle; all animations have static fallbacks.
- Keyboard-usable (focus rings visible); semantic HTML; aria-labels on icon buttons.
- Contrast AA on all palettes (light + dark).

## Theming

- Design tokens in `styles/tokens.css` (colors, radii, motion, spacing).
- Dark default + light optional; brand accent tokens ready before visual identity final.