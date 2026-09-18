# UI Design System

> Status: ACTIVE · Applies to the React frontend (Phase 1).

The product UI follows a single theme codenamed **"Midnight Bloom"** — a dark,
glass-first aesthetic with a violet→fuchsia brand gradient on a deep midnight
canvas. It is deliberately board-consistent so later phases (feed, chat,
notifications) reuse the same tokens and primitives instead of inventing new
ones.

## Principles

1. **Dark by default.** Midnight canvas lets colorful content pop and is easier
   on the eyes for long chat/feed sessions.
2. **Glass surfaces.** Cards use translucent fills with hairline borders and
   soft blur — depth without heavy shadows.
3. **One brand gradient.** Violet `brand` scores with a fuchsia sprinkle. Never
   introduce a second accent family casually.
4. **Smooth, subtle motion.** Micro-entrance animations (fade + rise), never
   anything that blocks interaction or feels bouncy.
5. **Mobile-first.** Single column on small screens; split layouts only from
   `lg`.

## Design tokens (Tailwind v4 `@theme`, in `src/index.css`)

| Token           | Value              | Usage                        |
| --------------- | ------------------ | ---------------------------- |
| `--color-midnight-950` | `#060714`    | Page canvas                  |
| `--color-slate-900/60` | —            | Surface fill (glass card)    |
| `--color-brand-500`    | `#8b5cf6`    | Gradient start (violet)      |
| `--color-brand-700`    | `#6d28d9`    | Gradient end (deeper violet) |
| `--color-fuchsia-300`  | `#f0abfc`    | Gradient shimmer / text      |
| `--font-sans`          | `Inter`      | Full UI typeface             |

Violet scale (`brand-50…950`) is the canonical accent ramp. Rose/rose-* is
reserved for destructive states (sign-out, errors), sky for verified badges.

## Component classes (`@layer components` in `src/index.css`)

| Class            | Purpose                                   |
| ---------------- | ----------------------------------------- |
| `.mesh-bg`       | Gradient mesh + drifting orbs backdrop    |
| `.glass-card`    | Blurred translucent card surface          |
| `.input-field`   | Base text input; combine `.is-invalid`    |
| `.btn-primary`   | Brand-gradient primary action (full width)|
| `.btn-secondary` | Hairline-bordered neutral/secondary action|
| `.text-gradient` | Brand gradient text (headlines)           |
| `.badge`         | Small pill chip (verified/meta)           |

## Layout & spacing

- Radius scale: inputs `rounded-xl`, cards `rounded-3xl`, brand mark `rounded-2xl`.
- Page gutter: `px-4 sm:px-8`; content max-width `max-w-5xl`.
- Vertical rhythm on auth: 32px between blocks (`space-y-8`).

## Motion conventions

- Entrance: `opacity 0, y 16px` → settle, `duration ~0.45s ease-out`.
- Buttons: `active:scale-[0.99]`, hover `brightness-110`.
- Orbs/keyframes live in `index.css` (no runtime JS), framer-motion handles
  component-level entrances.

## Dark/light

Dark-only. `color-scheme: dark` is forced; there is no light variant in Phase 1.

## Files owning the system

- `frontend/src/index.css` — tokens + component classes + keyframes.
- `frontend/src/components/AuthLayout.tsx` — shell (mesh, brand, split layout)
  and primitives: `AuthField`, `FormError`, `Spinner`, `BrandMark`.
- `frontend/src/pages/*` — consume component classes, never re-define raw
  colors inline.