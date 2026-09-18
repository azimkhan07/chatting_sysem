import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'

import { AuthField, AuthLayout, FormError, Spinner } from '@/components/AuthLayout'
import { ApiError, passwordApi } from '@/lib/api'

export default function ResetPassword() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [done, setDone] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setFormError(null)
    try {
      await passwordApi.reset({ email, token, password })
      setDone(true)
    } catch (error) {
      setFormError(
        error instanceof ApiError ? error.message : 'Unable to reset the password.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  if (!token || !email) {
    return (
      <AuthLayout>
        <div className="glass-card space-y-3 p-6 text-center">
          <p className="text-base font-semibold text-slate-100">Link incomplete</p>
          <p className="text-sm text-slate-400">
            This reset link is missing details. Request a fresh one below.
          </p>
          <Link
            to="/forgot-password"
            className="inline-block rounded-xl bg-gradient-to-r from-brand-500 to-fuchsia-500 px-4 py-2 text-sm font-semibold text-[#fff] transition hover:brightness-110"
          >
            Request a new link
          </Link>
        </div>
      </AuthLayout>
    )
  }

  return (
    <AuthLayout>
      <div className="space-y-5">
        <div className="space-y-1">
          <h2 className="text-2xl font-extrabold tracking-tight text-white">
            Choose a new password
          </h2>
          <p className="text-sm text-slate-400">
            For <span className="text-slate-200">{email}</span>
          </p>
        </div>

        {done ? (
          <div className="glass-card space-y-3 p-6 text-center">
            <p className="text-base font-semibold text-slate-100">Password updated</p>
            <p className="text-sm text-slate-400">
              You can now sign in with your new password.
            </p>
            <Link
              to="/login"
              className="inline-block rounded-xl bg-gradient-to-r from-brand-500 to-fuchsia-500 px-4 py-2 text-sm font-semibold text-[#fff] transition hover:brightness-110"
            >
              Go to sign in
            </Link>
          </div>
        ) : (
          <form
            onSubmit={handleSubmit}
            className="glass-card space-y-4 p-5 sm:p-6"
            noValidate
          >
            {formError ? <FormError message={formError} /> : null}

            <AuthField
              label="New password"
              name="password"
              type="password"
              value={password}
              placeholder="At least 8 characters"
              autoComplete="new-password"
              onChange={setPassword}
            />

            <button type="submit" disabled={submitting} className="btn-primary">
              {submitting ? <Spinner /> : 'Save new password'}
            </button>
          </form>
        )}
      </div>
    </AuthLayout>
  )
}