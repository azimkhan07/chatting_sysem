import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { ChatIcon } from '@/components/icons'
import { ApiError, invitesApi } from '@/lib/api'
import { chatConversation, path } from '@/lib/paths'
import type { Conversation } from '@/types/chat'

function describeJoinFailure(error: unknown): { title: string; detail: string } {
  if (error instanceof ApiError) {
    if (error.status === 429) {
      return {
        title: 'Too many attempts',
        detail: 'You tried this link a few times in a row. Wait a moment and try again.',
      }
    }
    if (error.status === 403) {
      return { title: 'Join blocked', detail: 'You no longer have access to this group.' }
    }
    if (error.status >= 500) {
      return { title: 'Server error', detail: 'We could not join you right now. Try again shortly.' }
    }
    return {
      title: 'Link no longer valid',
      detail: error.message || 'This invite may have expired or been revoked. Ask an admin for a new link.',
    }
  }

  return {
    title: 'Link no longer valid',
    detail: 'This invite may have expired or been revoked. Ask a group admin for a fresh link.',
  }
}

export default function JoinChat() {
  const navigate = useNavigate()
  const { code } = useParams<{ code: string }>()
  const trimmed = code?.trim() ?? ''

  const join = useQuery({
    queryKey: ['chat', 'join', trimmed],
    queryFn: async (): Promise<Conversation> => {
      if (trimmed === '') throw new Error('This invite link is incomplete.')
      return (await invitesApi.join(trimmed)).conversation
    },
    enabled: trimmed !== '',
    retry: false,
  })

  useEffect(() => {
    if (!join.isSuccess || join.data === undefined) return
    navigate(chatConversation(join.data.id), { replace: true })
  }, [join.isSuccess, join.data, navigate])

  const missingCode = trimmed === ''

  return (
    <div className="grid min-h-full place-items-center px-4">
      <div className="w-full max-w-sm rounded-3xl glass-card p-6 text-center">
        {missingCode || join.isError ? (
          <>
            <span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white/5 text-slate-400">
              <ChatIcon className="h-6 w-6" />
            </span>
            <p className="mt-3 text-base font-semibold text-slate-100">
              {missingCode ? 'That link is incomplete' : describeJoinFailure(join.error).title}
            </p>
            <p className="mt-1 text-sm text-slate-500">
              {missingCode
                ? 'The invite code is missing from the URL. Ask whoever shared it to send the full link.'
                : describeJoinFailure(join.error).detail}
            </p>
            <div className="mt-5 flex flex-col gap-2">
              {join.isError ? (
                <button
                  type="button"
                  onClick={() => void join.refetch()}
                  className="btn-primary w-full"
                >
                  {join.isFetching ? 'Retrying…' : 'Try again'}
                </button>
              ) : null}
              <button
                type="button"
                onClick={() => navigate(path('chat'))}
                className={
                  join.isError
                    ? 'rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/5'
                    : 'btn-primary w-full'
                }
              >
                Back to chats
              </button>
            </div>
          </>
        ) : (
          <>
            <Spinner className="mx-auto h-7 w-7" />
            <p className="mt-4 text-sm font-semibold text-slate-200">Joining the conversation…</p>
            <p className="mt-1 text-xs text-slate-500">Hang on while we add you to the group.</p>
          </>
        )}
      </div>
    </div>
  )
}
