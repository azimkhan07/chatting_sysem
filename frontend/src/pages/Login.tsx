import { useState } from 'react'
import { motion } from 'framer-motion'
import { Link, useNavigate } from 'react-router-dom'

import { AuthField, AuthLayout, FormError, Spinner } from '@/components/AuthLayout'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'

export default function Login() {
  const navigate = useNavigate()
  const login = useAuthStore((state) => state.login)
  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setFormError(null)
    try {
      await login({ identifier, password })
      navigate('/', { replace: true })
    } catch (error) {
      setFormError(
        error instanceof ApiError ? error.message : 'Unable to sign in.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout>
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45, ease: 'easeOut' }}
        className="space-y-8"
      >
        <div className="space-y-2">
          <h2 className="text-3xl font-extrabold tracking-tight text-white">
            Welcome back
          </h2>
          <p className="text-slate-400">
            Sign in to continue to your world.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5" noValidate>
          {formError ? <FormError message={formError} /> : null}

          <AuthField
            label="Username, email or mobile"
            name="identifier"
            value={identifier}
            placeholder="you@example.com"
            autoComplete="username"
            onChange={setIdentifier}
          />

          <AuthField
            label="Password"
            name="password"
            type="password"
            value={password}
            placeholder="••••••••"
            autoComplete="current-password"
            onChange={setPassword}
          />

          <button
            type="submit"
            disabled={submitting}
            className={[
              'flex w-full items-center justify-center gap-2 rounded-xl',
              'bg-gradient-to-r from-brand-500 to-brand-700 py-3 text-sm font-semibold text-white',
              'transition hover:brightness-110 active:scale-[0.99]',
              'focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/30',
              'disabled:cursor-not-allowed disabled:opacity-70',
            ].join(' ')}
          >
            {submitting ? <Spinner /> : 'Sign in'}
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