import { useState } from 'react'
import { motion } from 'framer-motion'
import { Link, useNavigate } from 'react-router-dom'

import { AuthField, AuthLayout, FormError, Spinner } from '@/components/AuthLayout'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'

/**
 * Two sign-in failures are not dead ends:
 *   - `ACCOUNT_DEACTIVATED` → the form becomes a reactivation form.
 *   - `ACCOUNT_DISABLED` (suspension) → the form becomes an appeal form, so a
 *     suspended user can ask support to review the ban from where they are.
 * Both keep the identifier and password that were already typed and proven.
 */
type Mode = 'sign-in' | 'reactivate' | 'appeal'

export default function Login() {
  const navigate = useNavigate()
  const login = useAuthStore((state) => state.login)
  const reactivate = useAuthStore((state) => state.reactivate)
  const appeal = useAuthStore((state) => state.appeal)
  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [appealText, setAppealText] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [appealSubmitted, setAppealSubmitted] = useState(false)
  const [mode, setMode] = useState<Mode>('sign-in')

  const reactivating = mode === 'reactivate'
  const appealing = mode === 'appeal'

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setFormError(null)
    try {
      if (appealing) {
        await appeal({ identifier, password, message: appealText })
        setAppealSubmitted(true)
        return
      }
      if (reactivating) {
        await reactivate({ identifier, password })
      } else {
        await login({ identifier, password })
      }
      navigate('/', { replace: true })
    } catch (error) {
      if (!reactivating && !appealing && error instanceof ApiError && error.code === 'ACCOUNT_DEACTIVATED') {
        setMode('reactivate')
        setFormError(null)
      } else if (!appealing && error instanceof ApiError && error.code === 'ACCOUNT_DISABLED') {
        setMode('appeal')
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

  if (appealSubmitted) {
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
              Appeal submitted
            </h2>
            <p className="text-sm text-slate-400">
              Our support team reviews suspension appeals quickly. You will be
              able to sign back in once it is approved.
            </p>
          </div>
          <div className="glass-card space-y-4 p-5 sm:p-6">
            <p className="rounded-xl bg-emerald-500/10 px-3 py-2.5 text-xs leading-relaxed text-emerald-200 ring-1 ring-emerald-500/30">
              Keep an eye on your notification list. You can come back to this
              screen and sign in again at any time.
            </p>
            <button
              type="button"
              onClick={() => {
                setAppealSubmitted(false)
                switchMode('sign-in')
              }}
              className="btn-primary"
            >
              Back to sign in
            </button>
          </div>
        </motion.div>
      </AuthLayout>
    )
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
            {appealing
              ? 'Challenge a suspension'
              : reactivating
                ? 'Your account is waiting'
                : 'Welcome back'}
          </h2>
          <p className="text-sm text-slate-400">
            {appealing
              ? "This account is suspended. Tell us why it shouldn't be and our support team will review it quickly."
              : reactivating
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

          {appealing ? (
            <p className="rounded-xl bg-sky-500/10 px-3 py-2.5 text-xs leading-relaxed text-sky-200 ring-1 ring-sky-500/30">
              Appeals go straight to our support team. File one and we will review it as fast as we
              can.
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
            {reactivating || appealing ? null : (
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

          {appealing ? (
            <div className="space-y-1">
              <label
                htmlFor="appeal-message"
                className="block text-sm font-medium text-slate-200"
              >
                Why should your account come back?
              </label>
              <textarea
                id="appeal-message"
                rows={4}
                required
                placeholder="Tell us what happened…"
                value={appealText}
                onChange={(e) => setAppealText(e.currentTarget.value)}
                className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-brand-400/60 focus:outline-none focus:ring-2 focus:ring-brand-400/30"
              />
            </div>
          ) : null}

          <button type="submit" disabled={submitting} className="btn-primary">
            {submitting ? (
              <Spinner />
            ) : appealing ? (
              'Submit appeal'
            ) : reactivating ? (
              'Reactivate account'
            ) : (
              'Sign in'
            )}
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

          {reactivating || appealing ? (
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