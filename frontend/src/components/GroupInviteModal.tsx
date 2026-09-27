import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { CheckIcon, CopyIcon, LinkIcon, ShieldCheckIcon, XIcon } from '@/components/icons'
import { ApiError, invitesApi } from '@/lib/api'
import { chatConversationJoin } from '@/lib/paths'

function expiryLabel(expiresAt: string | null): string | null {
  if (expiresAt === null) return null
  const target = new Date(expiresAt)
  const ms = target.getTime() - Date.now()
  if (Number.isNaN(ms)) return null
  if (ms <= 0) return 'Expired'

  const minutes = Math.round(ms / 60_000)
  if (minutes < 60) return `Expires in ${Math.max(minutes, 1)}m`
  const hours = Math.round(minutes / 60)
  if (hours < 48) return `Expires in ${hours}h`
  return `Expires ${target.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`
}

function failureText(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.message || fallback
  return fallback
}

export function GroupInviteModal({
  conversationId,
  groupName,
  onClose,
}: {
  conversationId: number
  groupName: string
  onClose: () => void
}) {
  const queryClient = useQueryClient()
  const [copied, setCopied] = useState(false)
  const [copyError, setCopyError] = useState<string | null>(null)
  const [confirmRevoke, setConfirmRevoke] = useState(false)

  const inviteQuery = useQuery({
    queryKey: ['chat', 'invite', conversationId],
    queryFn: () => invitesApi.current(conversationId),
  })

  const invite = inviteQuery.data?.invite ?? null

  const linkUrl = invite ? `${window.location.origin}${chatConversationJoin(invite.code)}` : null

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['chat', 'invite', conversationId] })
  }

  const copy = useCallback((text: string) => {
    const flash = (): void => {
      setCopyError(null)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 1800)
    }

    const fallback = (): void => {
      // Clipboard API is unavailable on http:// origins and older Safari.
      const node = document.createElement('textarea')
      node.value = text
      node.setAttribute('readonly', '')
      node.style.position = 'fixed'
      node.style.opacity = '0'
      document.body.appendChild(node)
      node.select()
      const ok = document.execCommand?.('copy') ?? false
      document.body.removeChild(node)

      if (ok) {
        flash()
        return
      }
      setCopyError('Copy failed — select the link and copy it manually.')
    }

    if (navigator.clipboard?.writeText) {
      void navigator.clipboard
        .writeText(text)
        .then(flash)
        .catch(fallback)
      return
    }

    fallback()
  }, [])

  const create = useMutation({
    mutationFn: () => invitesApi.create(conversationId),
    onSuccess: (data) => {
      invalidate()
      copy(`${window.location.origin}${chatConversationJoin(data.invite.code)}`)
    },
  })

  const revoke = useMutation({
    mutationFn: () => invitesApi.revoke(conversationId),
    onSuccess: () => {
      setCopied(false)
      setConfirmRevoke(false)
      invalidate()
    },
  })

  const share = (): void => {
    if (linkUrl === null) return
    if (navigator.share) {
      void navigator
        .share({ title: groupName, text: `Join ${groupName}`, url: linkUrl })
        .catch(() => undefined)
      return
    }
    copy(linkUrl)
  }

  useEffect(() => {
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])

  const expiry = invite ? expiryLabel(invite.expires_at) : null
  const error = create.error ?? revoke.error ?? null
  const loadError = inviteQuery.isError ? failureText(inviteQuery.error, 'Could not load the invite link.') : null

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm"
      onClick={(event) => {
        if (event.target === event.currentTarget) onClose()
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Invite people"
        className="w-full max-w-md rounded-3xl glass-card p-4"
      >
        <div className="flex items-center justify-between">
          <h2 className="text-base font-bold text-white">Invite people</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
          >
            <XIcon className="h-5 w-5" />
          </button>
        </div>

        <p className="mt-1 truncate text-sm text-slate-500">
          {groupName} — anyone with the link can join.
        </p>

        {loadError !== null ? (
          <div className="mt-4 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-3 py-2.5 text-xs text-rose-200">
            {loadError}
            <button
              type="button"
              onClick={() => void inviteQuery.refetch()}
              className="ml-2 font-semibold underline underline-offset-2"
            >
              Retry
            </button>
          </div>
        ) : null}

        {error !== null ? (
          <p className="mt-3 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">
            {failureText(error, create.error ? 'Could not create an invite link.' : 'Could not revoke the link.')}
          </p>
        ) : null}

        {inviteQuery.isPending && loadError === null ? (
          <div className="grid place-items-center py-14">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!inviteQuery.isPending || loadError !== null ? (
          <div className="mt-4">
            {linkUrl !== null ? (
              <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                <div className="flex items-center gap-2">
                  <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-500/15 text-brand-300">
                    <LinkIcon className="h-4 w-4" />
                  </span>
                  <p className="min-w-0 flex-1 truncate rounded-lg bg-black/30 px-2.5 py-1.5 font-mono text-xs text-slate-300">
                    {linkUrl}
                  </p>
                  <button
                    type="button"
                    onClick={() => copy(linkUrl)}
                    title="Copy link"
                    aria-label="Copy link"
                    className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-white/5 hover:text-white"
                  >
                    {copied ? (
                      <CheckIcon className="h-4 w-4 text-emerald-400" />
                    ) : (
                      <CopyIcon className="h-4 w-4" />
                    )}
                  </button>
                </div>

                <div className="mt-3 flex items-center justify-between gap-2">
                  <span className="min-w-0 truncate text-[11px] text-slate-500">
                    {expiry === 'Expired' ? (
                      <span className="font-semibold text-amber-300">{expiry}</span>
                    ) : expiry !== null ? (
                      expiry
                    ) : (
                      `Created by @${invite?.created_by.username}`
                    )}
                  </span>
                  <div className="flex shrink-0 gap-2">
                    <button type="button" onClick={share} className="btn-primary px-3 py-1.5 text-xs">
                      Share
                    </button>
                    {confirmRevoke ? (
                      <>
                        <button
                          type="button"
                          onClick={() => revoke.mutate()}
                          disabled={revoke.isPending}
                          className="rounded-lg bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/25 disabled:opacity-50"
                        >
                          {revoke.isPending ? 'Revoking…' : 'Confirm'}
                        </button>
                        <button
                          type="button"
                          onClick={() => setConfirmRevoke(false)}
                          className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/5"
                        >
                          Cancel
                        </button>
                      </>
                    ) : (
                      <button
                        type="button"
                        onClick={() => setConfirmRevoke(true)}
                        className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/10"
                      >
                        Revoke
                      </button>
                    )}
                  </div>
                </div>

                {copyError !== null ? (
                  <p className="mt-2 text-[11px] text-amber-300">{copyError}</p>
                ) : null}
              </div>
            ) : (
              <div className="grid place-items-center rounded-2xl border border-dashed border-white/10 px-4 py-14 text-center">
                <span className="grid h-12 w-12 place-items-center rounded-2xl bg-white/5 text-slate-400">
                  <ShieldCheckIcon className="h-6 w-6" />
                </span>
                <p className="mt-3 text-sm font-semibold text-slate-200">No active invite link</p>
                <p className="mt-1 max-w-xs text-xs text-slate-500">
                  Generate a link to share this group with anyone who isn't a member yet.
                </p>
                <button
                  type="button"
                  onClick={() => create.mutate()}
                  disabled={create.isPending}
                  className="btn-primary mt-4"
                >
                  {create.isPending ? <Spinner className="h-4 w-4" /> : null}
                  {create.isPending ? 'Creating…' : 'Create invite link'}
                </button>
              </div>
            )}
          </div>
        ) : null}
      </div>
    </div>
  )
}
