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
| Reels player  | native `<video>` on a progressive MP4/MOV/WebM file, custom UI; HLS.js only once adaptive streaming ships |
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

### Settings composition

`pages/Settings.tsx` is a **catalogue plus one screen**. It owns a `CATEGORIES` array where each
entry carries its id, label, blurb, icon and a `render()` for its screen. Everything else — the
mobile list, the desktop rail, the URL handling, the back behaviour — reads from that one array.
Adding a category is one entry plus one screen component.

The two widths are decided in **JS, not CSS**, from the same `768px` breakpoint:

- **Mobile** is a list of categories. Tapping one *pushes* a screen with a back arrow, because a
  phone has no room for a rail beside the content and a list that swaps in place gives the back
  arrow nothing to go back to. The list is a real destination in the history stack.
- **Desktop** is a sticky rail beside the content, and always shows a screen — Appearance by
  default, so the rail is never pointing at nothing.

This could not be done with CSS alone. Hiding a rail is styling; deciding that "no selection yet"
means *show Appearance* on desktop but *show the list* on mobile is behaviour. `useMediaQuery`
(`hooks/useMediaQuery.ts`) is built on `useSyncExternalStore` so the very first frame is already
the right shape — a `useState` + `useEffect` version would paint the phone layout on a desktop for
one frame before correcting itself, and that frame decides which navigation is on screen.

The selection lives in the query string (`/settings?section=security`), so a screen is linkable,
survives a refresh, and the browser back button walks out of it. The back arrow uses `replace`, so
arriving on a deep link does not trap the reader with a history entry they cannot leave.

Sizes step up at `sm`. Everything in a card — heading, description, padding, row height, button
height, field height, even the toggle track — is smaller on a phone, because a settings screen has
to fit a heading, a hint and a control inside one screen's worth of height.

`SettingCard` is its own file because it is the *page's* primitive: the page shows exactly one
card at a time and therefore cannot know its title, so the card owns the heading.

That split is not cosmetic. Every section needs `SettingCard`, `Row` and `FIELD`, and `Settings.tsx`
imports every section; if the primitives lived in the page, each section would have to import
the page that renders it. That is a cycle, and it fails in a way that is hard to read — a
`const` in the cycle body is in its temporal dead zone when the cycle runs.

```
components/settings/
├─ SettingCard.tsx           # the one-pane shell: title, description, footer
├─ SettingsUI.tsx            # Row, Toggle, FIELD, SubHeading, Divider — no domain knowledge
├─ AccountCenterSection.tsx  # profile facts, export, deactivate, delete
├─ PreferencesSection.tsx    # PrivacySection + NotificationsSection (one query, two screens)
├─ SecuritySection.tsx       # password change, per-device sessions
├─ FamilySection.tsx         # roster, roles, add/remove/leave/dissolve
├─ ContactSyncSection.tsx    # opt-in, paste-or-upload matching, results
└─ HelpSection.tsx           # support links, about, privacy requests
```

Four rules the sections follow:

- **Permissions come from the server.** `FamilyMemberResource` returns a per-viewer
  `permissions` block, so the client never re-derives "may this viewer remove that member"
  from its own role. One source of truth means the buttons cannot disagree with the API.
- **Irreversible actions re-check the password.** Deactivate and delete both require it, on
  the client as well as the server, because a hijacked token that can also wipe the account is
  the worst possible combination. The data export goes through `api.download()` rather than an
  `<a download>`: a navigation request cannot send the bearer token, so a plain link 401s.
  Deactivate and delete differ in colour by an explicit `tone` prop, not by their label text, so
  a copy change to "Delete" cannot quietly downgrade a destructive action to the amber one.
- **The opt-in stays on the device.** The contact-sync flag is `localStorage`, not a
  `user_settings` column, and both the chat Discover rail and the Settings screen read it
  through `lib/contactSync.ts`. A server-side copy of that flag would be a server-side copy of
  the thing the flag exists to avoid.
- **Only the visible screen mounts.** A list showing one screen at a time means each query
  drops its `enabled` gate. Leaving `enabled: open` behind would fetch every screen's data on
  first paint and then render the one you happened to be looking at.




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
- `:focus-visible` is defined once in `@layer base` (`index.css`), not per component — a
  component that forgets it no longer loses the focus ring.
- `@media (pointer: coarse)` bumps buttons/links to a 44px minimum touch target.
- `@media (prefers-reduced-motion: reduce)` clamps every animation and transition, so the
  ambient orbs, theme cross-fade and skeleton shimmer all stop when the OS asks.
- Shared `.empty-state` / `.empty-state-title` / `.empty-state-hint` and `.skeleton`
  utilities keep empty and loading states identical across pages.

## Theming

- Design tokens in `styles/tokens.css` (colors, radii, motion, spacing).
- Dark default + light optional; brand accent tokens ready before visual identity final.
- **Menu-driven remap:** a theme is expressed by remapping the palette (`white`, `slate-*`,
  `midnight-*`) per `data-theme`, so component classes do not change between themes.
- **Surface tokens are separate** (`--surface-card`, `--surface-raised`, `--surface-sunken`,
  `--surface-border`, `--surface-ring`). A card must not be built from `bg-slate-900/60`:
  in the light theme `slate-900` is `#ffffff`, so the card renders white-on-white and loses
  its edge. `.glass-card` and `.surface-raised` consume the tokens instead and keep their
  separation plus shadow in both themes.
- Full-height screens (chat) use the `.chat-fill` min-height chain on top of
  `.app-shell { height: 100dvh }` — see `AppShell.tsx`. Never a fixed `min-height` in px.