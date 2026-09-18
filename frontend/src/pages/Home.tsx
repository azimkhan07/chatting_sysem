import { useMutation, useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'

import AppShell, { ShellLoading } from '@/components/AppShell'
import { BellIcon, ChatIcon, CompassIcon, HomeIcon, SettingsIcon } from '@/components/icons'
import { api } from '@/lib/api'
import { path } from '@/lib/paths'
import { useAuthStore } from '@/stores/authStore'
import type { User } from '@/types/user'

interface MeResponseData {
  user: User
}

export default function Home() {
  const navigate = useNavigate()
  const sessionUser = useAuthStore((state) => state.user)
  const logout = useAuthStore((state) => state.logout)

  const meQuery = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => api.get<MeResponseData>('/auth/me'),
  })

  const logoutMutation = useMutation({
    mutationFn: () => logout(),
    onSuccess: () => navigate('/login', { replace: true }),
  })

  const activeUser = meQuery.data?.user ?? sessionUser

  if (meQuery.isPending === false && !activeUser) {
    return <SessionExpired />
  }

  if (!activeUser) {
    return <ShellLoading />
  }

  const nav = [
    { to: path('home'), label: 'Home', icon: <HomeIcon /> },
    { to: path('chat'), label: 'Chats', icon: <ChatIcon /> },
    { to: path('explore'), label: 'Explore', icon: <CompassIcon /> },
    { to: path('notifications'), label: 'Activity', icon: <BellIcon /> },
    { to: path('settings'), label: 'Settings', icon: <SettingsIcon /> },
  ]

  return (
    <AppShell nav={nav}>
      <div className="space-y-4">
        <section className="glass-card flex items-center gap-4 p-4 sm:p-5">
          <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-400 to-fuchsia-500 text-lg font-bold text-white shadow-lg shadow-brand-600/30">
            {activeUser.display_name.charAt(0).toUpperCase()}
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-lg font-extrabold tracking-tight text-white">
                {activeUser.display_name}
              </h1>
              {activeUser.is_verified ? (
                <span className="badge bg-sky-500/15 text-sky-300" title="Verified">
                  ✓ verified
                </span>
              ) : (
                <span className="badge bg-white/5 text-slate-400">
                  @{activeUser.username}
                </span>
              )}
            </div>
            <p className="mt-0.5 truncate text-xs text-slate-400">
              {activeUser.email ?? activeUser.mobile ?? 'No public contact yet'}
            </p>
          </div>
          <button
            type="button"
            onClick={() => logoutMutation.mutate()}
            disabled={logoutMutation.isPending}
            className="btn-secondary hidden sm:block"
          >
            {logoutMutation.isPending ? 'Signing out…' : 'Sign out'}
          </button>
        </section>

        <section className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-16 text-center">
          <span className="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 text-brand-300">
            <HomeIcon />
          </span>
          <p className="mt-4 text-xl font-semibold text-slate-200">
            Your feed is being prepared.
          </p>
          <p className="mt-1 max-w-sm text-sm text-slate-400">
            Posts, followers and conversations arrive in the next phase. You're
            all set for now.
          </p>
        </section>
      </div>
    </AppShell>
  )
}

function SessionExpired() {
  const navigate = useNavigate()

  return (
    <div className="grid h-svh place-items-center bg-midnight-950 px-4 text-center">
      <div>
        <p className="text-3xl font-extrabold tracking-tight text-white">
          Session expired
        </p>
        <p className="mt-2 text-sm text-slate-400">
          Your session is no longer valid. Please sign in again.
        </p>
        <button
          type="button"
          onClick={() => navigate('/login', { replace: true })}
          className="btn-primary mt-6 max-w-xs"
        >
          Go to sign in
        </button>
      </div>
    </div>
  )
}