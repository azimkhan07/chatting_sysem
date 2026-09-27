import { create } from 'zustand'

import { chatApi } from '@/lib/api'
import type { Conversation, PresenceHeartbeat } from '@/types/chat'

/**
 * Who is online, shared across the app so the inbox and the open thread always
 * agree on a dot. Seeded from every conversation payload the API returns and
 * then kept live by the `presence.changed` broadcast.
 */
interface PresenceState {
  /** userId -> online */
  online: Record<number, boolean>
  /** userId -> ISO timestamp of their last known activity. */
  lastSeen: Record<number, string | null>
  setOnline: (userId: number, isOnline: boolean, lastSeenAt?: string | null) => void
  setMany: (entries: Array<[number, boolean, string | null]>) => void
  /** Fold a conversation payload (inbox or thread fetch) into the store. */
  syncConversation: (conversation: Conversation) => void
  isOnline: (userId: number | undefined | null) => boolean
  lastSeenAt: (userId: number | undefined | null) => string | null
  reset: () => void
}

const empty = { online: {} as Record<number, boolean>, lastSeen: {} as Record<number, string | null> }

export const usePresenceStore = create<PresenceState>()((set, get) => ({
  ...empty,

  setOnline: (userId, isOnline, lastSeenAt) =>
    set((state) => ({
      online: { ...state.online, [userId]: isOnline },
      lastSeen: lastSeenAt === undefined
        ? state.lastSeen
        : { ...state.lastSeen, [userId]: lastSeenAt },
    })),

  setMany: (entries) =>
    set((state) => {
      const online = { ...state.online }
      const lastSeen = { ...state.lastSeen }
      for (const [userId, isOnline, seenAt] of entries) {
        online[userId] = isOnline
        if (seenAt !== null) lastSeen[userId] = seenAt
      }
      return { online, lastSeen }
    }),

  syncConversation: (conversation) =>
    set((state) => {
      const online = { ...state.online }
      const lastSeen = { ...state.lastSeen }

      for (const member of conversation.members) {
        online[member.user.id] = member.user.is_online
      }

      if (conversation.peer_presence) {
        online[conversation.peer_presence.user_id] = conversation.peer_presence.is_online
        if (conversation.peer_presence.last_seen_at) {
          lastSeen[conversation.peer_presence.user_id] = conversation.peer_presence.last_seen_at
        }
      }

      return { online, lastSeen }
    }),

  isOnline: (userId) => (userId ? get().online[userId] ?? false : false),
  lastSeenAt: (userId) => (userId ? get().lastSeen[userId] ?? null : null),
  reset: () => set(empty),
}))

/** Keep one user's presence fresh without waiting for a server round trip. */
export function markSelfOnline(userId: number): void {
  usePresenceStore.getState().setOnline(userId, true, new Date().toISOString())
}

/**
 * Tell the server we are still here. The 90s presence window means a client
 * with no traffic for two beats is treated as offline, so this runs on a timer
 * for as long as the tab is visible.
 */
export function beatPresence(userId: number): void {
  if (!userId) return

  const send = (): void => {
    markSelfOnline(userId)
    void chatApi
      .presence()
      .then((data: PresenceHeartbeat) => {
        if (data.last_seen_at) {
          usePresenceStore.getState().setOnline(userId, true, data.last_seen_at)
        }
      })
      .catch(() => undefined)
  }

  send()
}
