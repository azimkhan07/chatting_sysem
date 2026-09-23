# 01 — Vision & Features

## What we are building

A next-generation social platform: **chat + feed + reels + discovery + verification** in
one app. Aimed at the public to *feel* premium while being affordable to use.

- **Web-first** (v1). Mobile apps follow (v2).
- **Blue tick for everyone** — ₹1–₹5 / month, not ₹999 or corporate-only.
- **One signature feature** that Facebook, Instagram, X and WhatsApp **do not** have.
- AI voice assistant arrives in **v2**.

## Core modules (v1)

1. **Auth & Profiles**
   - Sign up / login (email + password, later OTP/social).
   - Profiles: username, display name, avatar, bio, cover, verified badge.
2. **Feed ("Home")**
   - Server-ranked post feed, cursor pagination, infinite scroll.
   - Content types: text, images, video, polls.
3. **Reels**
   - Vertical short-video player (YouTube-Short/Reels style), upload + full-screen viewing.
4. **Explorer ("Discover")**
   - Search + Explore grid: trending posts/reels, popular creators, hashtags.
5. **Share**
   - Share posts/reels to a group, a DM, or public "share to feed".
6. **Chat (1:1 + Groups)**
   - Real-time messaging, media messages, read receipts, typing, presence.
   - Groups scale-friendly: no hang even with many groups × many messages.
7. **Blue Tick (Verification)**
   - User requests verification, pays ₹1 or ₹5/month.
   - Admin reviews → badge goes live on profile/name.
   - Subscription auto-renews; users who stop paying lose the tick.
8. **Notifications**
   - Likes, comments, follows, shares, group mentions, verification updates.
   - Delivered in real time via WebSockets + persisted via DB (Queue-driven).

## Blue tick model (KEY DIFFERENTIATOR)

Most platforms: verification is expensive, manual, and mostly for celebs.
Our model makes it **transparent, cheap, and automated**:

| Plan | Price/month | Includes |
| ---- | ----------- | -------- |
| **Amtech Basic** | ₹1 | Blue badge, priority support queue, basic analytics |
| **Amtech Pro** | ₹5 | Blue badge, extra verification checks (KYC), advanced analytics, early features |

- Every user can afford it → viral "missed-call" level of public adoption.
- Revenue model is **subscription-first**, not ads-first (v1 has no ads).

## Signature feature (the thing FB/IG/X/WP don't have)

> Status: **DECIDED & SHIPPED — "Group Story Threads"** (2026-09-23).
> A 24-hour collaborative, multi-contributor timeline inside a group: every member posts
> entries (text or image), reacts (like/love/haha/wow/sad/angry), and at expiry the thread
> closes into a recap (entries, participants, reaction totals, top contributor).
> No major platform does collaborative ephemeral threads — this is the v1 hook.
> Shipped as roadmap item 9; see `02-roadmap.md` for the implementation summary.

Constraint list (evaluated against the pick):
- Not replicable by feed/chat/status paradigms alone — separate table + domain + realtime channel, not a message type.
- Drives daily engagement and/or paid (blue tick) adoption — group habit loop + recap FOMO (v2: boost recap reach to verified).
- Feasible for a small team in v1, owner-to-scale later — one migration, 4 endpoints, one scheduler command.

Shortlist directions considered (pick one or define a new one):
1. **Real-time "Live Room"** — audio/video drop-in spaces with guest co-hosting, tied to
   groups (WhatsApp groups are static text; IG lives are broadcast-only). *(deferred)*
2. **Group "Story Threads"** — ephemeral, collaborative, multi-contributor timelines inside
   a group with reaction-recaps (no major platform does collaborative ephemeral threads).
   *(SELECTED — shipped)*
3. **Verified-first social commerce** — marketplace only visible/buyable between verified
   users, with escrow and ₹5 verification as the trust layer. *(deferred)*
4. **AI "Mood Messenger"** — voice + text replies auto-summarized with emotional context
   (reserved for v2 AI voice assistant; could seed the data model now). *(reserved for v2)*

## Design language (UI/UX)

- **Different, smooth, animated.** Not a clone of any existing app.
- Web app is a PWA-capable SPA with fluid transitions (page = animated screen swipes,
  feed items stagger-in, infinite scroll debounced).
- Dark-mode-first aesthetic; accent color brand identity (TBD).
- Micro-interactions: like burst, share arc, chat bubbles that slide in, verified badge
  shimmer.
- Accessibility respected (focus states, reduced-motion toggle).
- Animation budget matters: 60fps, GPU-friendly transforms only (`transform/opacity`).

## Non-goals (for now)

- No ads in v1.
- No tight Instagram-style stories (deferred; "story threads" may become the signature
  feature instead).
- No heavy ML on-device; all intelligence is server-side.