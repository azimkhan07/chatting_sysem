import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

import type { Notification } from '@/types/notification'

const AUTH_STORAGE_KEY = 'amtechat.auth'
const DEV_KEY = 'amtechat-local-key'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

type ParsedAuth = { state?: { token?: string | null } }

function readToken(): string | null {
  try {
    const raw = localStorage.getItem(AUTH_STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as ParsedAuth
    return parsed.state?.token ?? null
  } catch {
    return null
  }
}

let instance: Echo<'reverb'> | null = null

export function echoInstance(): Echo<'reverb'> | null {
  if (instance) return instance

  window.Pusher = Pusher

  instance = new Echo<'reverb'>({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY ?? DEV_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    auth: {
      headers: {
        Authorization: `Bearer ${readToken() ?? ''}`,
        Accept: 'application/json',
      },
    },
  })

  return instance
}

export interface RealtimeNotificationPayload {
  notification: Notification
}