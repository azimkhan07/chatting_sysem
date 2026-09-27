import { useState } from 'react'
import { motion } from 'framer-motion'
import { Link, useNavigate } from 'react-router-dom'

import { AuthField, AuthLayout, FormError, Spinner } from '@/components/AuthLayout'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'

/**
 * `ACCOUNT_DEACTIVATED` is the one sign-in failure that is not the end of the
 * road, so the form switches into a reactivation mode instead of just showing
 * the error. The identifier and password stay filled in, because the user has
 * already typed and proven them.
 */
type Mode = 'sign-in' | 'reactivate'

export default function Login() {
  const navigate = useNavigate()
  const login = useAuthStore((state) => state.login)
  const reactivate = useAuthStore((state) => state.reactivate)
  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [mode, setMode] = useState<Mode>('sign-in')

  const reactivating = mode === 'reactivate'

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setFormError(null)
    try {
      if (reactivating) {
        await reactivate({ identifier, password })
      } else {
        await login({ identifier, password })
      }
      navigate('/', { replace: true })
    } catch (error) {
      if (!reactivating && error instanceof ApiError && error.code === 'ACCOUNT_DEACTIVATED') {
        setMode('reactivate')
        setFormError(null)
      } else {
        setFormError(error instanceof ApiError ? error.message : 'Unable to sign in.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  function switchMode(next: Mode) {
    setMode(next)
    setFormError(null)
  }

  return (
    <AuthLayout>
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45, ease: 'easeOut' }}
        className="space-y-5"
      >
        <div className="space-y-1">
          <h2 className="text-2xl font-extrabold tracking-tight text-white">
            {reactivating ? 'Your account is waiting' : 'Welcome back'}
          </h2>
          <p className="text-sm text-slate-400">
            {reactivating
              ? 'This account is deactivated. Reactivate it to pick up where you left off.'
              : 'Sign in to continue to your world.'}
          </p>
        </div>

        <form
          onSubmit={handleSubmit}
          className="glass-card space-y-4 p-5 sm:p-6"
          noValidate
        >
          {formError ? <FormError message={formError} /> : null}

          {reactivating ? (
            <p className="rounded-xl bg-amber-500/10 px-3 py-2.5 text-xs leading-relaxed text-amber-200 ring-1 ring-amber-500/30">
              Your posts, chats and profile are exactly where you left them. Reactivate to sign back
              in on this device.
            </p>
          ) : null}

          <AuthField
            label="Username, email or mobile"
            name="identifier"
            value={identifier}
            placeholder="you@example.com"
            autoComplete="username"
            onChange={setIdentifier}
          />

          <div className="space-y-1">
            <AuthField
              label="Password"
              name="password"
              type="password"
              value={password}
              placeholder="••••••••"
              autoComplete="current-password"
              onChange={setPassword}
            />
            {reactivating ? null : (
              <div className="text-right">
                <Link
                  to="/forgot-password"
                  className="text-xs text-slate-500 transition hover:text-brand-300"
                >
                  Forgot password?
                </Link>
              </div>
            )}
          </div>

          <button type="submit" disabled={submitting} className="btn-primary">
            {submitting ? <Spinner /> : reactivating ? 'Reactivate account' : 'Sign in'}
            {submitting ? null : (
              <svg
                viewBox="0 0 24 24"
                fill="none"
                className="h-4 w-4"
                aria-hidden="true"
              >
                <path
                  d="M5 12h14m0 0-6-6m6 6-6 6"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            )}
          </button>

          {reactivating ? (
            <button
              type="button"
              onClick={() => switchMode('sign-in')}
              disabled={submitting}
              className="w-full rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/5 disabled:opacity-60"
            >
              This is not my account
            </button>
          ) : null}
        </form>

        <p className="text-center text-sm text-slate-400">
          New to amteCHAT?{' '}
          <Link
            to="/register"
            className="font-semibold text-brand-400 transition hover:text-brand-300"
          >
            Create an account
          </Link>
        </p>
      </motion.div>
    </AuthLayout>
  )
}