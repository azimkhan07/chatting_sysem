import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { ChatIcon } from '@/components/icons'
import { invitesApi } from '@/lib/api'
import { chatConversation, path } from '@/lib/paths'

export default function JoinChat() {
  const navigate = useNavigate()
  const { code } = useParams<{ code: string }>()

  const join = useQuery({
    queryKey: ['chat', 'join', code],
    queryFn: async () => {
      if (!code) throw new Error('This invite link is incomplete.')
      return (await invitesApi.join(code)).conversation
    },
    enabled: !!code,
    retry: false,
  })

  useEffect(() => {
    if (!join.isSuccess || join.data === undefined) return
    navigate(chatConversation(join.data.id), { replace: true })
  }, [join.isSuccess, join.data, navigate])

  return (
    <div className="grid min-h-full place-items-center px-4">
      <div className="w-full max-w-sm rounded-3xl glass-card p-6 text-center">
        {join.isPending ? (
          <>
            <Spinner className="mx-auto h-7 w-7" />
            <p className="mt-4 text-sm font-semibold text-slate-200">Joining the conversation…</p>
          </>
        ) : join.isError ? (
          <>
            <span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white/5 text-slate-400">
              <ChatIcon className="h-6 w-6" />
            </span>
            <p className="mt-3 text-base font-semibold text-slate-100">Link no longer valid</p>
            <p className="mt-1 text-sm text-slate-500">
              This invite may have been revoked. Ask a group admin for a fresh link.
            </p>
            <button type="button" onClick={() => navigate(path('chat'))} className="btn-primary mt-5">
              Back to chats
            </button>
          </>
        ) : null}
      </div>
    </div>
  )
}