import { useEffect } from 'react'

import { echoInstance } from '@/lib/echo'
import { beatPresence, usePresenceStore } from '@/stores/presenceStore'
import type { Conversation } from '@/types/chat'

/**
 * The presence window is 90s server side, so a client with no traffic for two
 * beats is treated as offline. Heartbeating every 30s keeps the dot honest
 * without depending on the websocket being up.
 */
const HEARTBEAT_MS = 30_000

interface PresenceMember {
  id: number
}

function memberId(member: unknown): number | null {
  if (typeof member !== 'object' || member === null) return null
  const id = (member as PresenceMember).id
  return typeof id === 'number' ? id : null
}

/**
 * Mount once per authenticated session: beats presence on mount, every 30s, and
 * again whenever the tab comes back to the foreground.
 */
export function usePresenceHeartbeat(userId: number | undefined | null): void {
  useEffect(() => {
    if (!userId) return

    let timer: number | undefined

    const stop = (): void => {
      if (timer !== undefined) {
        window.clearInterval(timer)
        timer = undefined
      }
    }

    const start = (): void => {
      if (timer !== undefined) return
      beatPresence(userId)
      timer = window.setInterval(() => {
        if (document.visibilityState === 'visible') beatPresence(userId)
      }, HEARTBEAT_MS)
    }

    const onVisibility = (): void => {
      if (document.visibilityState === 'visible') beatPresence(userId)
    }

    start()
    document.addEventListener('visibilitychange', onVisibility)

    return () => {
      stop()
      document.removeEventListener('visibilitychange', onVisibility)
    }
  }, [userId])
}

/**
 * Join the presence channel of the open conversation so the thread header can
 * list who is actually in the room. The channel is left on unmount, otherwise
 * flipping between threads leaks a subscription per conversation.
 */
export function useConversationPresence(conversation: Conversation | null | undefined): void {
  const conversationId = conversation?.id
  const conversationType = conversation?.type

  useEffect(() => {
    const echo = echoInstance()
    if (!echo || conversationId === undefined) return

    const channelName = `presence-${conversationType}.${conversationId}`
    const channel = echo.join(channelName)
    const { setMany, setOnline } = usePresenceStore.getState()

    const applyHere = (members: unknown): void => {
      const ids = (Array.isArray(members) ? members : [])
        .map(memberId)
        .filter((id): id is number => id !== null)

      setMany(ids.map((id) => [id, true, null]))
    }

    channel
      .here(applyHere)
      .joining((member: unknown) => {
        const id = memberId(member)
        if (id !== null) setOnline(id, true)
      })
      .leaving((member: unknown) => {
        const id = memberId(member)
        if (id !== null) setOnline(id, false)
      })

    return () => {
      echo.leave(channelName)
    }
  }, [conversationId, conversationType])
}
