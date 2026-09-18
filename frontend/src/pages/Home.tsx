import { useMutation, useQuery } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { User } from '@/types/user'

interface MeResponseData {
  user: User
}

export default function Home() {
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const logout = useAuthStore((state) => state.logout)

  const meQuery = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: () => api.get<MeResponseData>('/auth/me'),
  })

  const logoutMutation = useMutation({
    mutationFn: () => logout(),
    onSuccess: () => navigate('/login', { replace: true }),
  })

  const activeUser = meQuery.data?.user ?? user

  if (meQuery.isPending === false && !activeUser) {
    return <Unauthorized />
  }

  if (!activeUser) {
    return (
      <div className="grid min-h-svh place-items-center bg-slate-950">
        <Spinner className="h-8 w-8" />
      </div>
    )
  }

  return (
    <div className="relative min-h-svh px-4 py-6 sm:px-8">
      <div className="mesh-bg">
        <div className="orb -top-40 right-0 h-96 w-96 bg-brand-600/25" />
        <div className="orb bottom-0 -left-40 h-96 w-96 bg-fuchsia-600/15 [animation-delay:-9s]" />
      </div>

      <div className="relative z-10 mx-auto max-w-5xl">
        <motion.header
          initial={{ opacity: 0, y: -8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, ease: 'easeOut' }}
          className="flex items-center justify-between"
        >
          <p className="text-lg font-bold tracking-tight text-white">
            amte<span className="text-brand-400">CHAT</span>
          </p>
          <button
            type="button"
            onClick={() => logoutMutation.mutate()}
            disabled={logoutMutation.isPending}
            className="btn-secondary"
          >
            {logoutMutation.isPending ? 'Signing out…' : 'Sign out'}
          </button>
        </motion.header>

        <motion.section
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45, delay: 0.1, ease: 'easeOut' }}
          className="glass-card mt-16 flex items-center gap-5 p-6 sm:p-8"
        >
          <div className="grid h-20 w-20 shrink-0 place-items-center rounded-3xl bg-gradient-to-br from-brand-400 to-fuchsia-500 text-3xl font-bold text-white shadow-xl shadow-brand-600/30">
            {activeUser.display_name.charAt(0).toUpperCase()}
          </div>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                {activeUser.display_name}
              </h1>
              {activeUser.is_verified ? (
                <span className="badge bg-sky-500/15 text-sky-300" title="Verified">
                  @{activeUser.username} ✓
                </span>
              ) : (
                <span className="badge bg-white/5 text-slate-400">
                  @{activeUser.username}
                </span>
              )}
            </div>
            <p className="mt-1 text-sm text-slate-400">
              {activeUser.email ?? activeUser.mobile ?? 'No public contact yet'}
            </p>
          </div>
        </motion.section>

        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45, delay: 0.2, ease: 'easeOut' }}
          className="mt-6 grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-20 text-center"
        >
          <span className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 text-brand-300">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              className="h-7 w-7"
              aria-hidden="true"
            >
              <path
                d="M4 6.5h16M4 12h16M4 17.5h10"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
              />
            </svg>
          </span>
          <p className="mt-6 text-2xl font-semibold text-slate-200 sm:text-3xl">
            Your feed is being prepared.
          </p>
          <p className="mt-2 max-w-md text-slate-400">
            Posts, followers and conversations are on the way in an upcoming
            phase. You're all set for now.
          </p>
        </motion.div>
      </div>
    </div>
  )
}

function Unauthorized() {
  const navigate = useNavigate()

  return (
    <div className="grid min-h-svh place-items-center bg-slate-950 px-4 text-center">
      <div>
        <p className="text-4xl font-extrabold tracking-tight text-white">
          Session expired
        </p>
        <p className="mt-2 text-slate-400">
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