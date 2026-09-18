/**
 * Opaque route tokens for the authenticated user app.
 *
 * Page paths are deliberately non-descriptive so the app topology is not
 * guessable from the URL. All internal links MUST route through `path()`.
 * The baseline guarantee for access control remains server-side
 * authorization (see docs/16-security-and-route-strategy.md).
 */
export const ROUTES = {
  home: '/',
  chat: '/a8f3c',
  explore: '/e9w2k',
  notifications: '/n5r7s',
  settings: '/s2v6q',
} as const

export type RouteKey = keyof typeof ROUTES

export function path(key: RouteKey): string {
  return ROUTES[key]
}