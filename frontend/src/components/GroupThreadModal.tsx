import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { ImageIcon, SendIcon, XIcon } from '@/components/icons'
import { threadsApi } from '@/lib/api'
import { echoInstance } from '@/lib/echo'
import { REACTION_EMOJI } from '@/lib/reactions'
import { useAuthStore } from '@/stores/authStore'
import type {
  RealtimeThreadEntryPayload,
  RealtimeThreadReactionPayload,
  ThreadDetail,
  ThreadEntry,
  ThreadReactionName,
} from '@/types/thread'
import { THREAD_REACTIONS } from '@/types/thread'

export function GroupThreadModal({
  conversationId,
  groupName,
  onClose,
}: {
  conversationId: number
  groupName: string
  onClose: () => void
}) {
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const [body, setBody] = useState('')
  const [media, setMedia] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const previewUrl = useMemo(() => (media ? URL.createObjectURL(media) : null), [media])

  const queryKey = ['threads', conversationId] as const

  const detail = useQuery({
    queryKey,
    queryFn: () => threadsApi.show(conversationId),
    refetchInterval: 10_000,
    refetchIntervalInBackground: true,
  })

  useEffect(() => {
    const echo = echoInstance()
    if (!echo) return

    const channel = echo.private(`group.${conversationId}`)

    channel.listen('.thread.entry.added', (payload: RealtimeThreadEntryPayload) => {
      queryClient.setQueryData<ThreadDetail>(queryKey, (current) => {
        if (current === undefined) return current
        const duplicate = current.entries.some((entry) => entry.id === payload.entry.id)
        if (duplicate) return current
        return {
          thread: { ...current.thread, entry_count: current.thread.entry_count + 1 },
          entries: [payload.entry, ...current.entries],
        }
      })
    })

    channel.listen('.thread.reaction.added', (payload: RealtimeThreadReactionPayload) => {
      queryClient.setQueryData<ThreadDetail>(queryKey, (current) => {
        if (current === undefined) return current
        return {
          ...current,
          entries: current.entries.map((entry) =>
            entry.id === payload.entry_id
              ? {
                  ...entry,
                  reactions: payload.totals,
                  my_reaction:
                    payload.user_id === me?.id ? (payload.reaction ?? null) : entry.my_reaction,
                }
              : entry,
          ),
        }
      })
    })

    return () => {
      channel.stopListening('.thread.entry.added')
      channel.stopListening('.thread.reaction.added')
    }
  }, [conversationId, me?.id, queryClient, queryKey])

  const addEntry = useMutation({
    mutationFn: () => threadsApi.addEntry(conversationId, { body, media }),
    onMutate: () => {
      setError(null)
    },
    onSuccess: (result) => {
      queryClient.setQueryData<ThreadDetail>(queryKey, (current) => {
        const next = current ?? { thread: result.thread, entries: [] }
        return {
          thread: result.thread,
          entries: [result.entry, ...next.entries.filter((entry) => entry.id !== result.entry.id)],
        }
      })
      setBody('')
      setMedia(null)
      if (fileRef.current) fileRef.current.value = ''
    },
    onError: (err) => setError(err.message),
  })

  const toggleReaction = useMutation({
    mutationFn: (variables: { entryId: number; reaction: ThreadReactionName }) =>
      threadsApi.toggleReaction(conversationId, variables.entryId, variables.reaction),
    onSuccess: (result, variables) => {
      queryClient.setQueryData<ThreadDetail>(queryKey, (current) => {
        if (current === undefined) return current
        return {
          ...current,
          entries: current.entries.map((entry) =>
            entry.id === variables.entryId
              ? {
                  ...entry,
                  reactions: result.totals,
                  my_reaction: result.reaction,
                }
              : entry,
          ),
        }
      })
    },
  })

  const thread = detail.data?.thread
  const entries = detail.data?.entries ?? []
  const active = thread?.active ?? false

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm">
      <div className="flex max-h-[85svh] w-full max-w-lg flex-col overflow-hidden rounded-3xl glass-card">
        <header className="flex items-center gap-3 border-b border-white/5 px-4 py-3">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-[#fff]">
            {groupName.charAt(0).toUpperCase()}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-bold text-white">Story thread · {groupName}</p>
            <p className="truncate text-xs text-slate-500">
              {active ? (
                <>
                  <span className="mr-1.5 inline-block h-2 w-2 rounded-full bg-emerald-400" />
                  Live for {hoursLeft(thread?.expires_at)}
                </>
              ) : (
                'This thread has ended'
              )}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
          >
            <XIcon className="h-5 w-5" />
          </button>
        </header>

        <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-3 py-4">
          {detail.isPending ? (
            <div className="grid place-items-center py-16">
              <Spinner className="h-6 w-6" />
            </div>
          ) : null}

          {!detail.isPending && entries.length === 0 && !active ? (
            <p className="py-12 text-center text-xs text-slate-500">
              Nobody contributed to this thread before it ended.
            </p>
          ) : null}

          {entries.map((entry) => (
            <ThreadEntryCard
              key={entry.id}
              entry={entry}
              onReact={(reaction) =>
                toggleReaction.mutate({ entryId: entry.id, reaction })
              }
            />
          ))}

          {!active && thread?.recap !== null ? (
            <RecapCard reactions={thread?.recap?.reactions ?? {}} />
          ) : null}
        </div>

        {active ? (
          <form
            onSubmit={(event) => {
              event.preventDefault()
              if (!body.trim() && !media) return
              addEntry.mutate()
            }}
            className="border-t border-white/5 p-3"
          >
            {media && previewUrl ? (
              <div className="mb-2 flex items-center gap-2">
                <img
                  src={previewUrl}
                  alt="Attachment preview"
                  className="h-14 w-14 rounded-xl object-cover"
                />
                <button
                  type="button"
                  onClick={() => {
                    setMedia(null)
                    if (fileRef.current) fileRef.current.value = ''
                  }}
                  className="rounded-lg px-2 py-1 text-xs text-slate-400 transition hover:bg-white/5 hover:text-white"
                >
                  Remove
                </button>
              </div>
            ) : null}

            <div className="flex items-end gap-2">
              <input
                ref={fileRef}
                type="file"
                accept="image/png,image/jpeg,image/webp,image/gif"
                aria-label="Attach image"
                className="hidden"
                onChange={(event) => setMedia(event.target.files?.[0] ?? null)}
              />
              <button
                type="button"
                onClick={() => fileRef.current?.click()}
                aria-label="Attach image"
                className="grid h-10 w-10 shrink-0 place-items-center rounded-full text-slate-400 transition hover:bg-white/5 hover:text-white"
              >
                <ImageIcon className="h-5 w-5" />
              </button>
              <textarea
                value={body}
                onChange={(event) => setBody(event.target.value)}
                placeholder="Drop an update into the group story…"
                rows={1}
                aria-label="Thread entry"
                className="min-h-[2.5rem] max-h-32 flex-1 resize-none rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
              />
              <button
                type="submit"
                disabled={(!body.trim() && !media) || addEntry.isPending}
                aria-label="Post entry"
                className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500 text-[#fff] transition hover:bg-brand-400 disabled:opacity-40"
              >
                {addEntry.isPending ? (
                  <Spinner className="h-4 w-4" />
                ) : (
                  <SendIcon className="h-5 w-5" />
                )}
              </button>
            </div>
            {error ? <p className="mt-2 text-xs text-rose-400">{error}</p> : null}
          </form>
        ) : null}
      </div>
    </div>
  )
}

function ThreadEntryCard({
  entry,
  onReact,
}: {
  entry: ThreadEntry
  onReact: (reaction: ThreadReactionName) => void
}) {
  const name = entry.user?.display_name ?? entry.user?.username ?? 'Member'
  const reactedCount = Object.values(entry.reactions).reduce((sum, count) => sum + count, 0)

  return (
    <article className="rounded-2xl border border-white/5 bg-white/[0.03] p-3">
      <div className="flex items-center gap-2">
        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-[#fff]">
          {name.charAt(0).toUpperCase()}
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-xs font-semibold text-slate-100">{name}</p>
          <p className="text-[10px] text-slate-500">{clockTime(entry.created_at)}</p>
        </div>
      </div>

      {entry.body ? (
        <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-100">
          {entry.body}
        </p>
      ) : null}

      {entry.media_url ? (
        <img
          src={entry.media_url}
          alt="Thread attachment"
          className="mt-2 max-h-64 w-full rounded-xl object-cover"
        />
      ) : null}

      <div className="mt-2 flex flex-wrap items-center gap-1">
        {THREAD_REACTIONS.map((reaction) => {
          const count = entry.reactions[reaction] ?? 0
          const mine = entry.my_reaction === reaction
          return (
            <button
              key={reaction}
              type="button"
              onClick={() => onReact(reaction)}
              title={reaction}
              className={[
                'flex items-center gap-1 rounded-full px-2 py-1 text-sm transition',
                mine
                  ? 'bg-brand-500/20 ring-1 ring-brand-400/50'
                  : 'opacity-70 hover:bg-white/5 hover:opacity-100',
              ].join(' ')}
            >
              <span>{REACTION_EMOJI[reaction]}</span>
              {count > 0 ? (
                <span className="text-[11px] font-semibold text-slate-300">{count}</span>
              ) : null}
            </button>
          )
        })}
        {reactedCount > 0 ? (
          <span className="ml-1 rounded-full bg-white/5 px-2 py-1 text-[10px] font-medium text-slate-400">
            {reactedCount} {reactedCount === 1 ? 'reaction' : 'reactions'}
          </span>
        ) : null}
      </div>
    </article>
  )
}

function RecapCard({ reactions }: { reactions: Record<string, number> }) {
  const active = THREAD_REACTIONS.filter((reaction) => (reactions[reaction] ?? 0) > 0)

  return (
    <section className="rounded-2xl border border-brand-400/20 bg-brand-500/10 p-4">
      <p className="text-xs font-bold tracking-wide text-brand-200 uppercase">Thread recap</p>
      <p className="mt-1 text-xs leading-relaxed text-slate-400">
        The collaborators' 24 hour story ticked over. Catch the highlights below.
      </p>
      {active.length > 0 ? (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {active.map((reaction) => (
            <span
              key={reaction}
              className="rounded-full bg-white/5 px-2.5 py-1 text-xs text-slate-200"
            >
              {REACTION_EMOJI[reaction]} {reactions[reaction]}
            </span>
          ))}
        </div>
      ) : null}
    </section>
  )
}

function hoursLeft(value: string | null | undefined): string {
  if (!value) return 'soon'
  const hours = (Date.parse(value) - Date.now()) / 3_600_000
  if (hours <= 0) return 'ending now'
  if (hours < 1) return `${Math.max(1, Math.round(hours * 60))} min`
  return `${Math.ceil(hours)}h`
}

function clockTime(value: string): string {
  return new Date(value).toLocaleTimeString(undefined, {
    hour: 'numeric',
    minute: '2-digit',
  })
}