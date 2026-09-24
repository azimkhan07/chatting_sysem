import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'

import { BrandMark, EyeIcon, EyeOffIcon, FormError, Spinner } from '@/components/AuthLayout'
import { ApiError } from '@/lib/api'
import { useAdminStore } from '@/stores/adminStore'

export default function AdminLogin() {
  const navigate = useNavigate()
  const token = useAdminStore((state) => state.token)
  const login = useAdminStore((state) => state.login)
  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [revealed, setRevealed] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  if (token) return <Navigate to="/admin" replace />

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setFormError(null)
    try {
      await login({ identifier, password })
      navigate('/admin', { replace: true })
    } catch (error) {
      setFormError(
        error instanceof ApiError
          ? error.message
          : 'Unable to sign in. Please try again.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="relative grid min-h-svh place-items-center overflow-hidden bg-midnight-950 px-4 py-8">
      <div className="mesh-bg">
        <div className="orb -top-32 -left-32 h-96 w-96 bg-brand-600/30" />
        <div className="orb bottom-0 right-0 h-96 w-96 bg-fuchsia-600/20 [animation-delay:-6s]" />
      </div>

      <div className="relative z-10 w-full max-w-md space-y-6">
        <div className="flex items-center justify-center gap-3">
          <BrandMark />
          <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-slate-300">
            Admin
          </span>
        </div>

        <div className="glass-card space-y-5 p-6 sm:p-8">
          <div className="space-y-1.5">
            <h2 className="text-2xl font-extrabold tracking-tight text-white">
              Admin console
            </h2>
            <p className="text-sm text-slate-400">
              Sign in with an account that holds the{' '}
              <span className="font-semibold text-brand-400">admin</span> role.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4" noValidate>
            {formError ? <FormError message={formError} /> : null}

            <div className="space-y-1.5">
              <label
                htmlFor="admin-identifier"
                className="block text-sm font-medium text-slate-300"
              >
                Email or username
              </label>
              <input
                id="admin-identifier"
                name="identifier"
                type="text"
                value={identifier}
                placeholder="admin@example.com"
                autoComplete="username"
                onChange={(event) => setIdentifier(event.target.value)}
                className="input-field"
              />
            </div>

            <div className="space-y-1.5">
              <label
                htmlFor="admin-password"
                className="block text-sm font-medium text-slate-300"
              >
                Password
              </label>
              <div className="relative">
                <input
                  id="admin-password"
                  name="password"
                  type={revealed ? 'text' : 'password'}
                  value={password}
                  placeholder="••••••••"
                  autoComplete="current-password"
                  onChange={(event) => setPassword(event.target.value)}
                  className="input-field pr-11"
                />
                <button
                  type="button"
                  onClick={() => setRevealed((current) => !current)}
                  aria-label={revealed ? 'Hide password' : 'Show password'}
                  tabIndex={-1}
                  className="absolute right-1 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-500 transition hover:text-slate-300"
                >
                  {revealed ? <EyeOffIcon /> : <EyeIcon />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="btn-primary w-full justify-center disabled:opacity-60"
            >
              {submitting ? <Spinner /> : null}
              {submitting ? 'Signing in…' : 'Enter console'}
            </button>
          </form>
        </div>

        <p className="text-center text-sm text-slate-500">
          <Link
            to="/"
            className="transition-colors hover:text-brand-400"
          >
            ← Back to amteCHAT
          </Link>
        </p>
      </div>
    </div>
  )
}