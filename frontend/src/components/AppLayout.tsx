import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { Navigate, Outlet, useNavigate } from 'react-router-dom'

import AppShell, { ShellLoading } from '@/components/AppShell'
import CallOverlay from '@/components/call/CallOverlay'
import {
  BellIcon,
  ChatIcon,
  CompassIcon,
  HomeIcon,
  SettingsIcon,
  UserIcon,
} from '@/components/icons'
import { api, ApiError, chatApi, notificationsApi } from '@/lib/api'
import { echoInstance } from '@/lib/echo'
import { path } from '@/lib/paths'
import { usePresenceHeartbeat } from '@/hooks/usePresence'
import { useAuthStore } from '@/stores/authStore'
import { useCallStore } from '@/stores/callStore'
import type {
  RealtimeCallAcceptedPayload,
  RealtimeCallEndedPayload,
  RealtimeCallOfferedPayload,
  RealtimeCallRejectedPayload,
} from '@/types/chat'
import type { User } from '@/types/user'

export default function AppLayout() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const token = useAuthStore((state) => state.token)
  const logout = useAuthStore((state) => state.logout)
  const setUser = useAuthStore((state) => state.setUser)

  const unreadQuery = useQuery({
    queryKey: ['notifications', 'unread'],
    queryFn: notificationsApi.unreadCount,
    enabled: token !== null,
    refetchInterval: 30_000,
  })

  const unread = unreadQuery.data?.unread ?? 0

  const chatUnreadQuery = useQuery({
    queryKey: ['chat', 'unread'],
    queryFn: chatApi.unreadTotal,
    enabled: token !== null,
    refetchInterval: 15_000,
  })

  const chatUnread = chatUnreadQuery.data?.unread ?? 0

  const NAV = [
    { to: path('home'), label: 'Home', icon: <HomeIcon /> },
    {
      to: path('chat'),
      label: 'Chats',
      icon: <ChatIcon />,
      badge: chatUnread || undefined,
    },
    { to: path('explore'), label: 'Explore', icon: <CompassIcon /> },
    { to: path('notifications'), label: 'Activity', icon: <BellIcon />, badge: unread || undefined },
    { to: path('profile'), label: 'Profile', icon: <UserIcon /> },
    { to: path('settings'), label: 'Settings', icon: <SettingsIcon /> },
  ]

  const meQuery = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => api.get<{ user: User }>('/auth/me'),
    enabled: token !== null,
    retry: false,
  })

  usePresenceHeartbeat(meQuery.data?.user.id)

  useEffect(() => {
    if (meQuery.data?.user) {
      setUser(meQuery.data.user)
    }
  }, [meQuery.data, setUser])

  useEffect(() => {
    const me = meQuery.data?.user
    if (!me) return
    const echo = echoInstance()
    if (!echo) return

    const channel = echo.private(`user.${me.id}`)
    channel.listen('.notification.created', () => {
      void queryClient.invalidateQueries({ queryKey: ['notifications', 'unread'] })
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })
    })
    channel.listen('.call.offered', (payload: RealtimeCallOfferedPayload) => {
      const { phase, conversationId, caller } = useCallStore.getState()
      // Already in a call for this thread - ignore a second offer.
      if (phase !== 'idle' && phase !== 'ended') return
      if (conversationId === payload.conversation_id) return
      if (caller?.id === payload.caller.id) return
      useCallStore.getState().ring({
        callId: payload.call_id,
        conversationId: payload.conversation_id,
        kind: payload.kind,
        room: payload.room,
        serverUrl: payload.server_url,
        caller: payload.caller,
      })
    })
    channel.listen('.call.accepted', (payload: RealtimeCallAcceptedPayload) => {
      const state = useCallStore.getState()
      if (state.callId !== payload.call_id) return
      if (state.caller === null) return
      // The callee confirmed; if we are the caller still ringing, go live.
      if (state.phase === 'outgoing') {
        useCallStore.getState().activate()
        useCallStore.getState().setBusy(false)
      }
    })
    channel.listen('.call.rejected', (payload: RealtimeCallRejectedPayload) => {
      const state = useCallStore.getState()
      if (state.callId !== payload.call_id) return
      if (state.phase === 'outgoing') {
        useCallStore.getState().end()
      }
    })
    channel.listen('.call.ended', (payload: RealtimeCallEndedPayload) => {
      const state = useCallStore.getState()
      if (state.callId !== payload.call_id) return
      useCallStore.getState().end()
    })

    return () => {
      channel.stopListening('.notification.created')
      channel.stopListening('.call.offered')
      channel.stopListening('.call.accepted')
      channel.stopListening('.call.rejected')
      channel.stopListening('.call.ended')
    }
  }, [meQuery.data?.user?.id, queryClient])

  useEffect(() => {
    if (meQuery.error instanceof ApiError && meQuery.error.status === 401) {
      void logout()
      navigate('/login', { replace: true })
    }
  }, [meQuery.error, logout, navigate])

  if (token === null) {
    return <Navigate to="/login" replace />
  }

  if (meQuery.isPending) {
    return (
      <div className="grid h-svh place-items-center bg-midnight-950">
        <ShellLoading />
      </div>
    )
  }

  if (meQuery.isError) {
    return <Navigate to="/login" replace />
  }

  return (
    <AppShell nav={NAV}>
      <Outlet />
      <CallOverlay />
    </AppShell>
  )
}