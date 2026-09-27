import type { ChatWallpaperOption } from '@/types/chat'

/**
 * Built-in wallpapers: gradient class names only, so switching a wallpaper
 * never touches the network and a chat has no extra storage or moderation
 * surface. The keys are validated server side; a key this map does not know
 * about (a future gallery upload) falls back to the default background rather
 * than rendering an empty thread.
 */
export const WALLPAPERS: Record<ChatWallpaperOption, { label: string; className: string }> = {
  solid: { label: 'Solid', className: 'bg-midnight-950' },
  aurora: {
    label: 'Aurora',
    className:
      'bg-[radial-gradient(120%_120%_at_0%_0%,rgba(124,58,237,0.35),transparent_55%),radial-gradient(110%_110%_at_100%_0%,rgba(236,72,153,0.30),transparent_60%),radial-gradient(120%_120%_at_50%_100%,rgba(14,165,233,0.28),transparent_60%)]',
  },
  sunset: {
    label: 'Sunset',
    className:
      'bg-[linear-gradient(160deg,rgba(251,146,60,0.28),transparent_55%),linear-gradient(200deg,rgba(236,72,153,0.30),transparent_60%)]',
  },
  ocean: {
    label: 'Ocean',
    className:
      'bg-[linear-gradient(180deg,rgba(14,165,233,0.30),transparent_60%),radial-gradient(100%_100%_at_100%_100%,rgba(8,145,178,0.35),transparent_60%)]',
  },
  neon: {
    label: 'Neon',
    className:
      'bg-[radial-gradient(90%_90%_at_10%_10%,rgba(34,197,94,0.28),transparent_55%),radial-gradient(90%_90%_at_90%_80%,rgba(168,85,247,0.32),transparent_60%)]',
  },
  blush: {
    label: 'Blush',
    className:
      'bg-[radial-gradient(110%_110%_at_0%_100%,rgba(244,114,182,0.30),transparent_60%),linear-gradient(150deg,rgba(251,191,36,0.16),transparent_55%)]',
  },
  forest: {
    label: 'Forest',
    className:
      'bg-[linear-gradient(170deg,rgba(16,185,129,0.26),transparent_58%),radial-gradient(100%_100%_at_80%_100%,rgba(101,163,13,0.28),transparent_60%)]',
  },
  graphite: {
    label: 'Graphite',
    className: 'bg-[linear-gradient(180deg,rgba(148,163,184,0.14),transparent_60%)]',
  },
}

export const DEFAULT_WALLPAPER: ChatWallpaperOption = 'solid'

/** Classes for the thread background, always including the base colour. */
export function wallpaperClassName(key: string | null | undefined): string {
  const entry = key === null || key === undefined ? undefined : WALLPAPERS[key]
  return ['bg-midnight-950', entry?.className ?? ''].filter(Boolean).join(' ')
}
