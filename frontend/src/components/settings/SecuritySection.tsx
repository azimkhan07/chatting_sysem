import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { SettingCard } from '@/components/settings/SettingCard'
import { Divider, FIELD, SubHeading } from '@/components/settings/SettingsUI'
import { settingsApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'

/**
 * Security: the password and every device that is signed in.
 *
 * A sessions list is only useful if it says *which* device, so the backend
 * stores the user agent in the token name and this reads it back out.
 */
export function SecuritySection() {
  const queryClient = useQueryClient()
  const logout = useAuthStore((state) => state.logout)
  const [editing, setEditing] = useState(false)
  const [current, setCurrent] = useState('')
  const [next, setNext] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState<string | null>(null)

  // No `enabled` gate: the rail decides which screen is mounted, so by the time
  // this renders the user is already looking at it.
  const sessions = useQuery({
    queryKey: ['settings', 'sessions'],
    queryFn: settingsApi.sessions,
  })

  const changePassword = useMutation({
    mutationFn: settingsApi.changePassword,
    onSuccess: (result) => {
      setEditing(false)
      setCurrent('')
      setNext('')
      setConfirm('')
      setDone(
        result.other_sessions_revoked > 0
          ? `Password changed. ${result.other_sessions_revoked} other ${
              result.other_sessions_revoked === 1 ? 'device was' : 'devices were'
            } signed out.`
          : 'Password changed.',
      )
      void queryClient.invalidateQueries({ queryKey: ['settings', 'sessions'] })
    },
  })

  const revoke = useMutation({
    mutationFn: (id: number) => settingsApi.revokeSession(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['settings', 'sessions'] }),
  })

  const revokeOthers = useMutation({
    mutationFn: settingsApi.revokeOtherSessions,
    onSuccess: (result) => {
      setDone(
        result.other_sessions_revoked > 0
          ? `Signed out of ${result.other_sessions_revoked} other ${
              result.other_sessions_revoked === 1 ? 'device' : 'devices'
            }.`
          : 'This was the only signed-in device.',
      )
      void queryClient.invalidateQueries({ queryKey: ['settings', 'sessions'] })
    },
  })

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError(null)
    setDone(null)
    try {
      await changePassword.mutateAsync({
        current_password: current,
        new_password: next,
        new_password_confirmation: confirm,
      })
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Could not change your password.')
    }
  }

  const rows = sessions.data?.sessions ?? []
  const others = rows.filter((session) => !session.is_current)

  return (
    <SettingCard
      title="Security"
      description="Your password, and every device that is currently signed in as you."
    >
      {done ? (
        <p className="mb-3 rounded-xl bg-emerald-500/10 px-3 py-2 text-xs text-emerald-300">{done}</p>
      ) : null}

      {editing ? (
        <form onSubmit={submit} className="space-y-2 sm:space-y-2.5">
          <input
            type="password"
            value={current}
            onChange={(event) => setCurrent(event.target.value)}
            placeholder="Current password"
            autoComplete="current-password"
            className={FIELD}
          />
          <input
            type="password"
            value={next}
            onChange={(event) => setNext(event.target.value)}
            placeholder="New password (8+ characters, with a number)"
            autoComplete="new-password"
            className={FIELD}
          />
          <input
            type="password"
            value={confirm}
            onChange={(event) => setConfirm(event.target.value)}
            placeholder="Confirm new password"
            autoComplete="new-password"
            className={FIELD}
          />
          {error ? <p className="text-xs text-rose-300">{error}</p> : null}
          <div className="flex gap-2 pt-1">
            <button
              type="button"
              onClick={() => {
                setEditing(false)
                setError(null)
              }}
              disabled={changePassword.isPending}
              className="flex-1 rounded-xl px-3 py-1.5 text-[13px] sm:px-4 sm:py-2 sm:text-sm font-semibold text-slate-300 transition hover:bg-white/5 disabled:opacity-60"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={changePassword.isPending || next.length < 8 || next !== confirm}
              className="flex-1 rounded-xl bg-brand-500 px-3 py-1.5 text-[13px] sm:px-4 sm:py-2 sm:text-sm font-semibold text-[#fff] transition hover:bg-brand-400 disabled:opacity-60"
            >
              {changePassword.isPending ? <Spinner className="mx-auto h-4 w-4" /> : 'Change password'}
            </button>
          </div>
        </form>
      ) : (
        <button
          type="button"
          onClick={() => {
            setEditing(true)
            setDone(null)
          }}
          className="w-full rounded-xl border border-white/10 px-3 py-2 text-[13px] sm:px-4 sm:py-2.5 sm:text-sm font-semibold text-slate-200 transition hover:border-brand-400/50 hover:text-brand-200"
        >
          Change password
        </button>
      )}

      <Divider>
        <div className="flex items-center justify-between gap-3">
          <SubHeading>Where you are signed in</SubHeading>
          {others.length > 0 ? (
            <button
              type="button"
              onClick={() => revokeOthers.mutate()}
              disabled={revokeOthers.isPending}
              className="text-[11px] font-semibold text-brand-300 transition hover:text-brand-200 disabled:opacity-60"
            >
              Sign out other devices
            </button>
          ) : null}
        </div>
      </Divider>

      {sessions.isPending ? (
        <div className="grid place-items-center py-5">
          <Spinner className="h-4 w-4" />
        </div>
      ) : null}

      {rows.length > 0 ? (
        <ul className="mt-1 divide-y divide-white/5">
          {rows.map((session) => (
            <li key={session.id} className="flex items-center gap-3 py-2.5">
              <span className="min-w-0 flex-1">
                <span className="flex items-center gap-1.5 text-[13px] sm:text-sm font-medium text-slate-100">
                  {session.device}
                  {session.is_current ? (
                    <span className="rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-emerald-300 uppercase">
                      This device
                    </span>
                  ) : null}
                </span>
                <span className="mt-0.5 block text-[11px] text-slate-500">
                  {session.last_used_at
                    ? `Last used ${new Date(session.last_used_at).toLocaleString()}`
                    : 'Not used yet'}
                </span>
              </span>
              {session.is_current ? (
                <button
                  type="button"
                  onClick={() => void logout()}
                  className="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold text-slate-400 transition hover:text-slate-200"
                >
                  Sign out
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => revoke.mutate(session.id)}
                  disabled={revoke.isPending}
                  className="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-[11px] font-semibold text-slate-300 transition hover:border-rose-500/50 hover:text-rose-300 disabled:opacity-60"
                >
                  Sign out
                </button>
              )}
            </li>
          ))}
        </ul>
      ) : null}

      {!sessions.isPending && rows.length === 0 ? (
        <p className="py-4 text-[13px] sm:text-sm text-slate-400">No signed-in devices found.</p>
      ) : null}
    </SettingCard>
  )
}
