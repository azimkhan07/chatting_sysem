import { useState } from 'react'
import { Link } from 'react-router-dom'

import { AuthField, AuthLayout, FormError, Spinner } from '@/components/AuthLayout'
import { ApiError, passwordApi } from '@/lib/api'

export default function ForgotPassword() {
  const [email, setEmail] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [sent, setSent] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setFormError(null)
    try {
      await passwordApi.sendLink(email)
      setSent(true)
    } catch (error) {
      setFormError(
        error instanceof ApiError ? error.message : 'Unable to send the link.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout>
      <div className="space-y-5">
        <div className="space-y-1">
          <h2 className="text-2xl font-extrabold tracking-tight text-white">
            Forgot password?
          </h2>
          <p className="text-sm text-slate-400">
            Enter the email on your account and we'll send a reset link.
          </p>
        </div>

        {sent ? (
          <div className="glass-card space-y-3 p-6 text-center">
            <p className="text-base font-semibold text-slate-100">Check your inbox</p>
            <p className="text-sm text-slate-400">
              If the email exists, a reset link is on its way to{' '}
              <span className="text-slate-200">{email}</span>. It expires in 60
              minutes.
            </p>
            <Link
              to="/login"
              className="inline-block rounded-xl bg-gradient-to-r from-brand-500 to-fuchsia-500 px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110"
            >
              Back to sign in
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
              label="Email"
              name="email"
              type="email"
              value={email}
              placeholder="you@example.com"
              autoComplete="email"
              onChange={setEmail}
            />

            <button type="submit" disabled={submitting} className="btn-primary">
              {submitting ? <Spinner /> : 'Send reset link'}
            </button>
          </form>
        )}

        <p className="text-center text-sm text-slate-400">
          Remembered it?{' '}
          <Link
            to="/login"
            className="font-semibold text-brand-400 transition hover:text-brand-300"
          >
            Sign in
          </Link>
        </p>
      </div>
    </AuthLayout>
  )
}