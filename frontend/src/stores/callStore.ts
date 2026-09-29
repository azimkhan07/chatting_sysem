import { create } from 'zustand'

import type { CallKind, CallUser } from '@/types/chat'

/**
 * Global call state. Exactly one call can be in flight at a time - the signal
 * is conversation-scoped and the LiveKit room is per-call, so overlapping
 * calls from two threads would fight over one UI. The store holds the call
 * bookkeeping; media lives in the LiveKit Room instance owned by CallOverlay.
 */
export type CallPhase =
  | 'idle'
  // I am calling someone; waiting for them to pick up.
  | 'outgoing'
  // Someone is calling me; I am deciding.
  | 'incoming'
  // Media is flowing.
  | 'active'
  // Finished ringing / hanging up - brief, then resets to idle.
  | 'ended'

interface CallState {
  phase: CallPhase
  callId: number | null
  conversationId: number | null
  kind: CallKind
  room: string | null
  serverUrl: string | null
  token: string | null
  caller: CallUser | null
  busy: boolean
  start: (params: {
    callId: number
    conversationId: number
    kind: CallKind
    room: string
    serverUrl: string
    token: string
  }) => void
  /** Someone else offered a call; store their identity for the ring screen. */
  ring: (params: {
    callId: number
    conversationId: number
    kind: CallKind
    room: string
    serverUrl: string
    caller: CallUser
  }) => void
  activate: () => void
  setBusy: (busy: boolean) => void
  end: () => void
  reset: () => void
}

export const useCallStore = create<CallState>()((set) => ({
  phase: 'idle',
  callId: null,
  conversationId: null,
  kind: 'audio',
  room: null,
  serverUrl: null,
  token: null,
  caller: null,
  busy: false,

  start: ({ callId, conversationId, kind, room, serverUrl, token }) =>
    set({
      phase: 'outgoing',
      callId,
      conversationId,
      kind,
      room,
      serverUrl,
      token,
      caller: null,
      busy: true,
    }),

  ring: ({ callId, conversationId, kind, room, serverUrl, caller }) =>
    set({
      phase: 'incoming',
      callId,
      conversationId,
      kind,
      room,
      serverUrl,
      token: null,
      caller,
      busy: false,
    }),

  activate: () => set({ phase: 'active', busy: false }),

  setBusy: (busy) => set({ busy }),

  end: () => set({ phase: 'ended', busy: false }),

  reset: () =>
    set({
      phase: 'idle',
      callId: null,
      conversationId: null,
      room: null,
      serverUrl: null,
      token: null,
      caller: null,
      busy: false,
    }),
}))