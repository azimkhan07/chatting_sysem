import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { Navigate, Outlet, useNavigate } from 'react-router-dom'

import AppShell, { ShellLoading } from '@/components/AppShell'
import {
  BellIcon,
  ChatIcon,
  CompassIcon,
  HomeIcon,
  SettingsIcon,
  UserIcon,
} from '@/components/icons'
import { api, ApiError } from '@/lib/api'
import { path } from '@/lib/paths'
import { useAuthStore } from '@/stores/authStore'
import type { User } from '@/types/user'

const NAV = [
  { to: path('home'), label: 'Home', icon: <HomeIcon /> },
  { to: path('chat'), label: 'Chats', icon: <ChatIcon /> },
  { to: path('explore'), label: 'Explore', icon: <CompassIcon /> },
  { to: path('notifications'), label: 'Activity', icon: <BellIcon /> },
  { to: path('profile'), label: 'Profile', icon: <UserIcon /> },
  { to: path('settings'), label: 'Settings', icon: <SettingsIcon /> },
]

export default function AppLayout() {
  const navigate = useNavigate()
  const token = useAuthStore((state) => state.token)
  const logout = useAuthStore((state) => state.logout)
  const setUser = useAuthStore((state) => state.setUser)

  const meQuery = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => api.get<{ user: User }>('/auth/me'),
    enabled: token !== null,
    retry: false,
  })

  useEffect(() => {
    if (meQuery.data?.user) {
      setUser(meQuery.data.user)
    }
  }, [meQuery.data, setUser])

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
    </AppShell>
  )
}