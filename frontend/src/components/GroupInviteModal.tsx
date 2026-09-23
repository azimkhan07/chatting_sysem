import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { CheckIcon, CopyIcon, LinkIcon, ShieldCheckIcon, XIcon } from '@/components/icons'
import { invitesApi } from '@/lib/api'
import { chatConversationJoin } from '@/lib/paths'

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

  const inviteQuery = useQuery({
    queryKey: ['chat', 'invite', conversationId],
    queryFn: () => invitesApi.current(conversationId),
  })

  const invite = inviteQuery.data?.invite ?? null

  const linkUrl = invite
    ? `${window.location.origin}${chatConversationJoin(invite.code)}`
    : null

  const create = useMutation({
    mutationFn: () => invitesApi.create(conversationId),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ['chat', 'invite', conversationId] })
      copyText(`${window.location.origin}${chatConversationJoin(data.invite.code)}`)
    },
  })

  const revoke = useMutation({
    mutationFn: () => invitesApi.revoke(conversationId),
    onSuccess: () => {
      setCopied(false)
      void queryClient.invalidateQueries({ queryKey: ['chat', 'invite', conversationId] })
    },
  })

  function copyText(text: string) {
    if (navigator.clipboard) {
      void navigator.clipboard.writeText(text).then(() => {
        setCopied(true)
        window.setTimeout(() => setCopied(false), 1600)
      })
    }
  }

  function share() {
    if (linkUrl === null) return
    if (navigator.share) {
      void navigator.share({ title: groupName, text: `Join ${groupName}`, url: linkUrl }).catch(() => undefined)
      return
    }
    copyText(linkUrl)
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-3xl glass-card p-4">
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

        {inviteQuery.isPending ? (
          <div className="grid place-items-center py-14">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!inviteQuery.isPending ? (
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
                    onClick={() => copyText(linkUrl)}
                    title="Copy link"
                    aria-label="Copy link"
                    className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-white/5 hover:text-white"
                  >
                    {copied ? <CheckIcon className="h-4 w-4 text-emerald-400" /> : <CopyIcon className="h-4 w-4" />}
                  </button>
                </div>

                <div className="mt-3 flex items-center justify-between gap-2">
                  <span className="text-[11px] text-slate-500">
                    Created by @{invite?.created_by.username}
                  </span>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={share}
                      className="btn-primary px-3 py-1.5 text-xs"
                    >
                      Share
                    </button>
                    <button
                      type="button"
                      onClick={() => revoke.mutate()}
                      disabled={revoke.isPending}
                      className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/10 disabled:opacity-50"
                    >
                      {revoke.isPending ? 'Revoking…' : 'Revoke'}
                    </button>
                  </div>
                </div>
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