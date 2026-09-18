import { useState } from 'react'
import { motion } from 'framer-motion'
import { Link, useNavigate } from 'react-router-dom'

import {
  AuthField,
  AuthLayout,
  FormError,
  Spinner,
} from '@/components/AuthLayout'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'

export default function Register() {
  const navigate = useNavigate()
  const register = useAuthStore((state) => state.register)

  const [values, setValues] = useState({
    username: '',
    display_name: '',
    email: '',
    mobile: '',
    password: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  function update(field: keyof typeof values) {
    return (value: string) => setValues((prev) => ({ ...prev, [field]: value }))
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (submitting) return

    setSubmitting(true)
    setFormError(null)
    try {
      await register({
        username: values.username,
        display_name: values.display_name,
        email: values.email || undefined,
        mobile: values.mobile || undefined,
        password: values.password,
      })
      navigate('/', { replace: true })
    } catch (error) {
      if (error instanceof ApiError && error.field) {
        setFormError(`${error.field}: ${error.message}`)
      } else {
        setFormError(
          error instanceof ApiError ? error.message : 'Unable to create account.',
        )
      }
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
        className="space-y-6"
      >
        <div className="space-y-2">
          <h2 className="text-3xl font-extrabold tracking-tight text-white">
            Create your account
          </h2>
          <p className="text-slate-400">
            Takes less than a minute. Join the conversation.
          </p>
        </div>

        <form
          onSubmit={handleSubmit}
          className="glass-card space-y-5 p-6 sm:p-8"
          noValidate
        >
          {formError ? <FormError message={formError} /> : null}

          <AuthField
            label="Username"
            name="username"
            value={values.username}
            placeholder="e.g. aarav09"
            autoComplete="username"
            onChange={update('username')}
          />

          <AuthField
            label="Display name"
            name="display_name"
            value={values.display_name}
            placeholder="What should people call you?"
            onChange={update('display_name')}
          />

          <AuthField
            label="Email"
            name="email"
            type="email"
            value={values.email}
            placeholder="you@example.com"
            autoComplete="email"
            onChange={update('email')}
          />

          <AuthField
            label="Mobile"
            name="mobile"
            type="tel"
            value={values.mobile}
            placeholder="+91 98765 43210"
            autoComplete="tel"
            onChange={update('mobile')}
          />

          <AuthField
            label="Password"
            name="password"
            type="password"
            value={values.password}
            placeholder="At least 8 characters"
            autoComplete="new-password"
            hint="Min 8 characters."
            onChange={update('password')}
          />

          <button type="submit" disabled={submitting} className="btn-primary">
            {submitting ? <Spinner /> : 'Create account'}
          </button>
        </form>

        <p className="text-center text-sm text-slate-400">
          Already have an account?{' '}
          <Link
            to="/login"
            className="font-semibold text-brand-400 transition hover:text-brand-300"
          >
            Sign in
          </Link>
        </p>
      </motion.div>
    </AuthLayout>
  )
}