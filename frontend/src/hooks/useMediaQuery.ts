import { useCallback, useSyncExternalStore } from 'react'

/**
 * Subscribe to a media query.
 *
 * Used where the *behaviour* has to differ by width, not just the styling — a
 * settings screen that is a list on a phone and a rail on a desktop has to know
 * which one it is, and CSS alone cannot answer that.
 *
 * `useSyncExternalStore` rather than `useState` + `useEffect`: the media query
 * list is an external store, and this hook reads it during render instead of
 * painting a stale value first and correcting it in an effect. That also means
 * the very first frame is already correct, which matters when the decision
 * controls which navigation a reader is looking at.
 */
export function useMediaQuery(query: string): boolean {
  const subscribe = useCallback(
    (onChange: () => void) => {
      const list = window.matchMedia(query)
      list.addEventListener('change', onChange)
      return () => list.removeEventListener('change', onChange)
    },
    [query],
  )

  const getSnapshot = useCallback(() => window.matchMedia(query).matches, [query])

  // Only consulted during server rendering, where there is no window. The Vite
  // SPA has no SSR, so this never runs in practice — but a hook that throws on
  // import in a future prerender pass is a nasty surprise to debug.
  const getServerSnapshot = () => false

  return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot)
}
