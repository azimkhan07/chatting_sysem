import { useMutation, useQuery } from '@tanstack/react-query'
import { useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { FIELD, Panel, Row } from '@/components/settings/SettingsUI'
import { settingsApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { AccountType } from '@/types/user'

const ACCOUNT_TYPE_LABELS: Record<AccountType, string> = {
  personal: 'Personal',
  professional: 'Professional',
  business: 'Business',
}

/**
 * Account Center: who you are, and what happens to the account.
 *
 * The destructive half is deliberately behind a password field. A one-tap
 * "Delete account" is a support ticket waiting to happen, and a one-tap
 * "Deactivate" is worse, because the user cannot undo what they did not
 * understand.
 */
export function AccountCenterSection({ open, onToggle }: { open: boolean; onToggle: () => void }) {
  const logout = useAuthStore((state) => state.logout)
  const [deactivating, setDeactivating] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const account = useQuery({
    queryKey: ['account'],
    queryFn: settingsApi.account,
    enabled: open,
  })

  const deactivate = useMutation({
    mutationFn: settingsApi.deactivate,
  })
  const remove = useMutation({
    mutationFn: settingsApi.deleteAccount,
  })

  const [exporting, setExporting] = useState(false)

  async function doExport() {
    setExporting(true)
    setError(null)
    try {
      const { blob, filename } = await settingsApi.exportData()
      // Object URL + a synthetic click: the only way to save a blob that was
      // fetched with an auth header rather than navigated to.
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      link.click()
      URL.revokeObjectURL(url)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Could not prepare your data.')
    } finally {
      setExporting(false)
    }
  }

  async function doDeactivate(password: string) {
    setError(null)
    try {
      await deactivate.mutateAsync(password)
      setDeactivating(false)
      await logout()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Could not deactivate.')
    }
  }

  async function doDelete(password: string) {
    setError(null)
    try {
      await remove.mutateAsync(password)
      await logout()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Could not delete the account.')
    }
  }

  const data = account.data?.account
  const joined = data?.member_since
    ? new Date(data.member_since).toLocaleDateString(undefined, {
        month: 'short',
        year: 'numeric',
      })
    : '—'

  return (
    <Panel title="Account Center" caption="Your details and your sign-in" open={open} onToggle={onToggle}>
      {account.isPending ? (
        <div className="grid place-items-center py-6">
          <Spinner className="h-5 w-5" />
        </div>
      ) : null}

      {data ? (
        <>
          <dl className="space-y-2.5">
            <Row label="Username" value={`@${data.username}`} />
            <Row label="Display name" value={data.display_name} />
            <Row label="Email" value={data.email ?? 'Not set'} />
            <Row label="Mobile" value={data.mobile ?? 'Not set'} />
            <Row
              label="Account type"
              value={ACCOUNT_TYPE_LABELS[data.account_type] ?? data.account_type}
            />
            {data.family ? (
              <Row
                label="Family"
                value={`${data.family.name} · ${data.family.members} ${
                  data.family.members === 1 ? 'member' : 'members'
                }`}
              />
            ) : null}
            <Row label="Member since" value={joined} />
            <Row label="Posts" value={data.counts.posts.toLocaleString()} />
            <Row
              label="Followers"
              value={data.counts.followers.toLocaleString()}
            />
          </dl>

          <div className="mt-5">
            <button
              type="button"
              onClick={() => void doExport()}
              disabled={exporting}
              className="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-brand-400/50 hover:text-brand-200 disabled:opacity-60"
            >
              {exporting ? <Spinner className="h-4 w-4" /> : null}
              {exporting ? 'Preparing your data…' : 'Download my data'}
            </button>
            <p className="mt-1.5 text-[11px] leading-relaxed text-slate-500">
              A single JSON file with your profile, posts, connections and preferences. Passwords
              and tokens are never included.
            </p>
          </div>

          <div className="mt-5 space-y-2 border-t border-white/5 pt-4">
            {deactivating ? (
              <ConfirmForm
                title="Deactivate your account?"
                body="You will be signed out everywhere. Your profile, posts and chats stay exactly as they are, and you can come back at any time with your password."
                confirmLabel="Deactivate"
                busy={deactivate.isPending}
                error={error}
                onCancel={() => {
                  setDeactivating(false)
                  setError(null)
                }}
                onConfirm={doDeactivate}
              />
            ) : (
              <button
                type="button"
                onClick={() => setDeactivating(true)}
                className="w-full rounded-xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-amber-400/50 hover:text-amber-200"
              >
                Deactivate account
              </button>
            )}

            {deleteOpen ? (
              <ConfirmForm
                title="Permanently delete your account?"
                body="Your profile is erased and every device is signed out. Posts and messages that other people already have stay visible, but nothing links back to you. This cannot be undone."
                confirmLabel="Delete permanently"
                busy={remove.isPending}
                error={error}
                onCancel={() => {
                  setDeleteOpen(false)
                  setError(null)
                }}
                onConfirm={doDelete}
              />
            ) : (
              <button
                type="button"
                onClick={() => setDeleteOpen(true)}
                className="w-full rounded-xl border border-rose-500/40 px-4 py-2.5 text-sm font-semibold text-rose-300 transition hover:bg-rose-500/10"
              >
                Delete account
              </button>
            )}
          </div>
        </>
      ) : null}

      {!account.isPending && !data ? (
        <p className="text-sm text-slate-400">Could not load your account details.</p>
      ) : null}
    </Panel>
  )
}

/** Password re-confirmation for an irreversible action. */
function ConfirmForm({
  title,
  body,
  confirmLabel,
  busy,
  error,
  onCancel,
  onConfirm,
}: {
  title: string
  body: string
  confirmLabel: string
  busy: boolean
  error: string | null
  onCancel: () => void
  onConfirm: (password: string) => void
}) {
  const [password, setPassword] = useState('')

  return (
    <div className="rounded-xl bg-rose-500/[0.07] p-3.5 ring-1 ring-rose-500/30">
      <p className="text-sm font-semibold text-rose-200">{title}</p>
      <p className="mt-1 text-xs leading-relaxed text-slate-300">{body}</p>
      <input
        type="password"
        value={password}
        onChange={(event) => setPassword(event.target.value)}
        placeholder="Your password"
        autoComplete="current-password"
        className={`${FIELD} mt-3`}
      />
      {error ? <p className="mt-1.5 text-xs text-rose-300">{error}</p> : null}
      <div className="mt-3 flex gap-2">
        <button
          type="button"
          onClick={onCancel}
          disabled={busy}
          className="flex-1 rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5 disabled:opacity-60"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={() => onConfirm(password)}
          disabled={busy || password.length === 0}
          className="flex-1 rounded-xl bg-rose-500/90 px-4 py-2 text-sm font-semibold text-[#fff] transition hover:bg-rose-500 disabled:opacity-60"
        >
          {busy ? <Spinner className="mx-auto h-4 w-4" /> : confirmLabel}
        </button>
      </div>
    </div>
  )
}
