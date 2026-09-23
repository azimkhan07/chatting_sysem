import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type InfiniteData,
} from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { GroupInviteModal } from '@/components/GroupInviteModal'
import { GroupThreadModal } from '@/components/GroupThreadModal'
import {
  ArrowLeftIcon,
  BellIcon,
  LinkIcon,
  PlusIcon,
  SendIcon,
  SparkleIcon,
  UsersIcon,
  XIcon,
} from '@/components/icons'
import { chatApi, usersApi } from '@/lib/api'
import { echoInstance } from '@/lib/echo'
import { chatConversation, path } from '@/lib/paths'
import { emptyReactions, REACTIONS, REACTION_EMOJI, type ReactionName } from '@/lib/reactions'
import { useAuthStore } from '@/stores/authStore'
import type {
  ChatMessagesPage,
  Conversation,
  ConversationMessage,
  MessageKind,
  ReadReceipt,
  RealtimeDeletedPayload,
  RealtimeMessagePayload,
  RealtimeReactionPayload,
  RealtimeTypingPayload,
} from '@/types/chat'
import type { User } from '@/types/user'

const INBOX_POLL_MS = 15_000
const THREAD_POLL_MS = 3_000
const TYPING_THROTTLE_MS = 3_000

export default function ChatPage() {
  const navigate = useNavigate()
  const { conversationId } = useParams<{ conversationId: string }>()
  const activeId = conversationId ? Number(conversationId) : null
  const [composer, setComposer] = useState<'dm' | 'group' | null>(null)

  return (
    <div className="flex h-full min-h-0 flex-1 bg-midnight-950">
      <InboxPane
        activeId={activeId}
        className={activeId === null ? 'flex' : 'hidden md:flex'}
        onNewDm={() => setComposer('dm')}
        onNewGroup={() => setComposer('group')}
        onSelect={(id) => navigate(chatConversation(id))}
      />

      {activeId === null ? (
        <div className="hidden min-w-0 flex-1 flex-col items-center justify-center gap-3 px-8 text-center md:flex">
          <span className="grid h-14 w-14 place-items-center rounded-2xl bg-white/5 text-slate-400">
            <UsersIcon className="h-7 w-7" />
          </span>
          <p className="text-base font-semibold text-slate-200">Select a conversation</p>
          <p className="max-w-xs text-sm text-slate-500">
            Start a private chat or create a group to keep the conversation going.
          </p>
          <button
            type="button"
            onClick={() => setComposer('dm')}
            className="btn-primary mt-2 w-auto px-6"
          >
            New message
          </button>
        </div>
      ) : (
        <ThreadPane conversationId={activeId} onBack={() => navigate(path('chat'))} />
      )}

      {composer !== null ? (
        <NewChatModal
          mode={composer}
          onClose={() => setComposer(null)}
          onCreated={(conversation) => navigate(chatConversation(conversation.id))}
        />
      ) : null}
    </div>
  )
}

function InboxPane({
  activeId,
  className,
  onNewDm,
  onNewGroup,
  onSelect,
}: {
  activeId: number | null
  className: string
  onNewDm: () => void
  onNewGroup: () => void
  onSelect: (id: number) => void
}) {
  const inbox = useQuery({
    queryKey: ['chat', 'conversations'],
    queryFn: chatApi.conversations,
    refetchInterval: INBOX_POLL_MS,
  })

  const conversations = inbox.data?.conversations ?? []

  return (
    <aside
      className={`${className} w-full shrink-0 flex-col border-b border-white/5 md:w-80 md:border-r md:border-b-0`}
    >
      <div className="flex items-center justify-between px-4 pt-4 pb-2">
        <h1 className="text-lg font-extrabold tracking-tight text-white">Chats</h1>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={onNewGroup}
            title="New group"
            aria-label="New group"
            className="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
          >
            <UsersIcon className="h-5 w-5" />
          </button>
          <button
            type="button"
            onClick={onNewDm}
            title="New message"
            aria-label="New message"
            className="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
          >
            <PlusIcon className="h-5 w-5" />
          </button>
        </div>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
        {inbox.isPending ? (
          <div className="grid place-items-center py-16">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!inbox.isPending && conversations.length === 0 ? (
          <div className="grid place-items-center rounded-2xl border border-dashed border-white/10 px-4 py-12 text-center">
            <p className="text-sm font-semibold text-slate-300">No conversations yet</p>
            <p className="mt-1 text-xs text-slate-500">
              Start a private message or create a group.
            </p>
          </div>
        ) : null}

        {conversations.length > 0 ? (
          <div className="space-y-0.5">
            {conversations.map((conversation) => (
              <ConversationRow
                key={conversation.id}
                conversation={conversation}
                active={conversation.id === activeId}
                onClick={() => onSelect(conversation.id)}
              />
            ))}
          </div>
        ) : null}
      </div>
    </aside>
  )
}

function ConversationRow({
  conversation,
  active,
  onClick,
}: {
  conversation: Conversation
  active: boolean
  onClick: () => void
}) {
  const name = displayName(conversation)
  const preview = previewFor(conversation)
  const unread = conversation.unread_count ?? 0

  return (
    <button
      type="button"
      onClick={onClick}
      className={[
        'flex w-full items-center gap-3 rounded-2xl p-2.5 text-left transition',
        active ? 'bg-white/[0.07]' : 'hover:bg-white/[0.04]',
      ].join(' ')}
    >
      <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-[#fff]">
        {name.charAt(0).toUpperCase()}
      </span>
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-2">
          <p className="truncate text-sm font-semibold text-slate-100">{name}</p>
          <span className="shrink-0 text-[11px] text-slate-500">
            {relativeTime(conversation.updated_at)}
          </span>
        </div>
        <div className="mt-0.5 flex items-center justify-between gap-2">
          <p className="truncate text-xs text-slate-400">{preview}</p>
          {unread > 0 ? (
            <span className="shrink-0 rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-[#fff]">
              {unread > 99 ? '99+' : unread}
            </span>
          ) : null}
        </div>
      </div>
    </button>
  )
}

function ThreadPane({
  conversationId,
  onBack,
}: {
  conversationId: number
  onBack: () => void
}) {
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const [draft, setDraft] = useState('')
  const [optimistic, setOptimistic] = useState<ConversationMessage[]>([])
  const [composerError, setComposerError] = useState<string | null>(null)
  const [threadOpen, setThreadOpen] = useState(false)
  const [inviteOpen, setInviteOpen] = useState(false)
  const pinnedRef = useRef(true)
  const scrollRef = useRef<HTMLDivElement>(null)
  const lastTypingRef = useRef(0)
  const readWatermarkRef = useRef(0)
  const [typingUsers, setTypingUsers] = useState<Record<string, number>>({})
  const typingTimeoutsRef = useRef(new Map<string, ReturnType<typeof setTimeout>>())

  useEffect(() => {
    const timers = typingTimeoutsRef.current
    return () => {
      for (const timer of timers.values()) clearTimeout(timer)
      timers.clear()
    }
  }, [])

  const conversationQuery = useQuery({
    queryKey: ['chat', 'conversation', conversationId],
    queryFn: () => chatApi.show(conversationId),
  })

  const messagePages = useInfiniteQuery({
    queryKey: ['chat', 'messages', conversationId],
    queryFn: ({ pageParam }) => chatApi.messages(conversationId, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
    refetchInterval: THREAD_POLL_MS,
    refetchIntervalInBackground: true,
  })

  const serverMessages = useMemo(
    () => buildChronological(messagePages.data?.pages ?? []).filter((message) => message.id > 0),
    [messagePages.data],
  )

  const myReadWatermarkId = useMemo(() => {
    let watermark = 0
    for (const message of serverMessages) {
      if (message.sender?.id === me?.id && message.read) watermark = Math.max(watermark, message.id)
    }
    return watermark
  }, [serverMessages, me?.id])

  const messages = useMemo(() => {
    const seen = new Set<number>()
    const output: ConversationMessage[] = []
    for (const message of serverMessages) {
      if (seen.has(message.id)) continue
      seen.add(message.id)
      output.push(message)
    }
    for (const temp of optimistic) {
      const duplicated = serverMessages.some(
        (message) =>
          message.sender?.id === me?.id &&
          message.body === temp.body &&
          Math.abs(Date.parse(message.created_at) - Date.parse(temp.created_at)) < 5_000,
      )
      if (!duplicated) output.push(temp)
    }
    output.sort(
      (a, b) =>
        Date.parse(a.created_at) - Date.parse(b.created_at) || (a.id > 0 && b.id > 0 ? a.id - b.id : 0),
    )
    return output
  }, [serverMessages, optimistic, me?.id])

  const conversation = conversationQuery.data?.conversation ?? null
  const latestServerId = messages.reduce((max, message) => Math.max(max, message.id), 0)
  const isMuted = conversation?.muted ?? false
  const isModerator =
    conversation?.members.some(
      (member) =>
        member.user.id === me?.id && (member.role === 'owner' || member.role === 'admin'),
    ) ?? false

  const messagesQueryKey = ['chat', 'messages', conversationId] as const

  function applyReaction(messageId: number, totals: Record<ReactionName, number>, mine: ReactionName | null) {
    queryClient.setQueryData<InfiniteData<ChatMessagesPage, string | undefined>>(
      messagesQueryKey,
      (current) => mutateMessageInPages(current, messageId, (message) => ({
        ...message,
        reactions: totals,
        my_reaction: mine,
      })),
    )
  }

  function removeMessageById(messageId: number) {
    queryClient.setQueryData<InfiniteData<ChatMessagesPage, string | undefined>>(
      messagesQueryKey,
      (current) => {
        if (current === undefined) return current
        return {
          ...current,
          pages: current.pages.map((page) => ({
            ...page,
            messages: page.messages.filter((message) => message.id !== messageId),
          })),
          pageParams: current.pageParams,
        }
      },
    )
  }

  const reactMutation = useMutation({
    mutationFn: (variables: { messageId: number; reaction: ReactionName }) =>
      chatApi.react(conversationId, variables.messageId, variables.reaction),
    onSuccess: (result) => {
      applyReaction(result.message_id, result.reactions, result.reaction)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (messageId: number) => chatApi.deleteMessage(conversationId, messageId),
    onSuccess: (_, messageId) => {
      removeMessageById(messageId)
      void queryClient.invalidateQueries({ queryKey: ['chat'] })
    },
  })

  useEffect(() => {
    const echo = echoInstance()
    if (!echo || conversation === null) return

    const prefix = conversation.type === 'dm' ? 'dm' : 'group'
    const channel = echo.private(`${prefix}.${conversationId}`)

    channel.listen('.message.sent', (payload: RealtimeMessagePayload) => {
      setTypingUsers((current) => {
        const key = `${payload.message.conversation_id}:${payload.message.sender?.id ?? 0}`
        if (!(key in current)) return current
        const next = { ...current }
        delete next[key]
        return next
      })
      queryClient.setQueryData<InfiniteData<ChatMessagesPage, string | undefined>>(
        messagesQueryKey,
        (current) => {
          if (current === undefined) return current
          const duplicate = current.pages.some((page) =>
            page.messages.some((message) => message.id === payload.message.id),
          )
          if (duplicate) return current
          const firstPage = current.pages[0]
          const pages = current.pages.slice()
          pages[0] = {
            ...firstPage,
            messages: [payload.message, ...(firstPage?.messages ?? [])],
          }
          return { ...current, pages, pageParams: current.pageParams }
        },
      )
    })

    channel.listen('.message.reaction.changed', (payload: RealtimeReactionPayload) => {
      queryClient.setQueryData<InfiniteData<ChatMessagesPage, string | undefined>>(
        messagesQueryKey,
        (current) =>
          mutateMessageInPages(current, payload.message_id, (message) => ({
            ...message,
            reactions: payload.totals,
            my_reaction:
              payload.user_id === me?.id ? (payload.reaction ?? null) : message.my_reaction,
          })),
      )
    })

    channel.listen('.message.deleted', (payload: RealtimeDeletedPayload) => {
      removeMessageById(payload.message_id)
      void queryClient.invalidateQueries({ queryKey: ['chat'] })
    })

    channel.listen('.typing', (payload: RealtimeTypingPayload) => {
      if (payload.conversation_id !== conversationId || payload.user_id === me?.id) return
      const key = `${payload.conversation_id}:${payload.user_id}`
      const pending = typingTimeoutsRef.current.get(key)
      if (pending) clearTimeout(pending)
      typingTimeoutsRef.current.set(
        key,
        window.setTimeout(() => {
          typingTimeoutsRef.current.delete(key)
          setTypingUsers((current) => {
            if (!(key in current)) return current
            const next = { ...current }
            delete next[key]
            return next
          })
        }, 3_500),
      )
      setTypingUsers((current) => ({ ...current, [key]: payload.user_id }))
    })

    return () => {
      channel.stopListening('.message.sent')
      channel.stopListening('.message.reaction.changed')
      channel.stopListening('.message.deleted')
      channel.stopListening('.typing')
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [conversationId, conversation?.type, me?.id, queryClient])
  const mutationVariablesRef = useRef<string | null>(null)

  const loadOlderMessages = useCallback(() => {
    if (!messagePages.isFetchingNextPage) void messagePages.fetchNextPage()
  }, [messagePages.isFetchingNextPage, messagePages.fetchNextPage])

  const sendMutation = useMutation({
    mutationFn: (variables: { body: string; clientId: string }) =>
      chatApi.send(conversationId, variables.body, variables.clientId),
    onMutate: (variables) => {
      if (me === null) return
      setOptimistic((current) => [
        ...current,
        {
          id: -Date.now(),
          conversation_id: conversationId,
          sender: {
            id: me.id,
            username: me.username,
            display_name: me.display_name,
            avatar_url: me.avatar_url,
            is_verified: me.is_verified,
          },
          type: 'text',
          body: variables.body,
          media_url: null,
          read: false,
          read_by: [],
          reactions: emptyReactions(),
          my_reaction: null,
          created_at: new Date().toISOString(),
          client_id: variables.clientId,
        },
      ])
      pinnedRef.current = true
    },
    onSuccess: () => {
      setOptimistic((current) =>
        current.filter((message) => message.client_id !== mutationVariablesRef.current),
      )
      void queryClient.invalidateQueries({ queryKey: ['chat'] })
    },
    onError: (error) => {
      setOptimistic((current) =>
        current.filter((message) => message.client_id !== mutationVariablesRef.current),
      )
      setComposerError(error.message)
    },
  })

  function handleSend() {
    const body = draft.trim()
    if (!body || sendMutation.isPending) return
    const clientId = crypto.randomUUID()
    mutationVariablesRef.current = clientId
    setComposerError(null)
    setDraft('')
    sendMutation.mutate({ body, clientId })
  }

  useEffect(() => {
    void queryClient.invalidateQueries({ queryKey: ['chat'] })
  }, [conversationId, queryClient])

  useEffect(() => {
    const node = scrollRef.current
    if (!node || !pinnedRef.current) return
    node.scrollTop = node.scrollHeight
  }, [messages.length, optimistic.length])

  useEffect(() => {
    setOptimistic([])
    readWatermarkRef.current = 0
    pinnedRef.current = true
  }, [conversationId])

  useEffect(() => {
    if (latestServerId === 0 || latestServerId === readWatermarkRef.current) return
    readWatermarkRef.current = latestServerId
    const timer = window.setTimeout(() => {
      void chatApi
        .markRead(conversationId, latestServerId)
        .then(() => {
          void queryClient.invalidateQueries({ queryKey: ['chat'] })
        })
        .catch(() => {
          readWatermarkRef.current = 0
        })
    }, 600)
    return () => window.clearTimeout(timer)
  }, [latestServerId, conversationId, queryClient])

  function maybeSendTyping() {
    const now = Date.now()
    if (now - lastTypingRef.current < TYPING_THROTTLE_MS) return
    lastTypingRef.current = now
    void chatApi.typing(conversationId).catch(() => undefined)
  }

  const name = conversation ? displayName(conversation) : 'Chat'
  const subtitle = conversation?.type === 'group' ? `${conversation.members_count} members` : 'Direct message'

  const typingNames = useMemo(() => {
    const prefix = `${conversationId}:`
    const ids = Object.keys(typingUsers)
      .filter((key) => key.startsWith(prefix))
      .map((key) => Number(key.split(':')[1]))
    if (ids.length === 0) return []
    const memberById = new Map(conversation?.members.map((member) => [member.user.id, member.user]))
    return ids.map((id) => memberById.get(id)?.display_name ?? `#${id}`)
  }, [typingUsers, conversation?.members, conversationId])

  const muteMutation = useMutation({
    mutationFn: (muted: boolean) => chatApi.setMuted(conversationId, muted),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['chat'] })
    },
  })

  return (
    <section className="flex min-w-0 flex-1 flex-col">
      <header className="flex items-center gap-3 border-b border-white/5 px-3 py-2.5">
        <button
          type="button"
          onClick={onBack}
          aria-label="Back to chats"
          className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white md:hidden"
        >
          <ArrowLeftIcon className="h-5 w-5" />
        </button>
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-[#fff]">
          {name.charAt(0).toUpperCase()}
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-slate-100">{name}</p>
          {typingNames.length > 0 ? (
            <p className="truncate text-xs font-medium text-brand-300">
              {typingNames.length === 1
                ? `${typingNames[0]} is typing…`
                : `${typingNames.length} people are typing…`}
            </p>
          ) : (
            <p className="truncate text-xs text-slate-500">{subtitle}</p>
          )}
        </div>
        {conversation?.type === 'group' && isModerator ? (
          <button
            type="button"
            onClick={() => setInviteOpen(true)}
            title="Invite people"
            aria-label="Invite people to this group"
            className="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
          >
            <LinkIcon className="h-5 w-5" />
          </button>
        ) : null}
        {conversation?.type === 'group' ? (
          <button
            type="button"
            onClick={() => setThreadOpen(true)}
            title="Group story thread"
            aria-label="Open group story thread"
            className="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-brand-300"
          >
            <SparkleIcon className="h-5 w-5" />
          </button>
        ) : null}
        <button
          type="button"
          onClick={() => muteMutation.mutate(!isMuted)}
          title={isMuted ? 'Unmute' : 'Mute'}
          aria-label={isMuted ? 'Unmute conversation' : 'Mute conversation'}
          className={[
            'rounded-lg p-2 transition',
            isMuted
              ? 'bg-brand-500/15 text-brand-300'
              : 'text-slate-400 hover:bg-white/5 hover:text-white',
          ].join(' ')}
        >
          <BellIcon className="h-5 w-5" />
        </button>
      </header>

      {conversationQuery.isPending ? (
        <div className="grid flex-1 place-items-center">
          <Spinner className="h-6 w-6" />
        </div>
      ) : null}

      {conversationQuery.isError && !conversationQuery.isPending ? (
        <div className="grid flex-1 place-items-center px-6 text-center">
          <p className="text-sm text-slate-400">
            This conversation isn't available to you.
          </p>
        </div>
      ) : null}

      {conversation && !conversationQuery.isPending ? (
        <>
          <div
            ref={scrollRef}
            onScroll={() => {
              const node = scrollRef.current
              if (!node) return
              pinnedRef.current = node.scrollHeight - node.scrollTop - node.clientHeight < 80
            }}
            className="min-h-0 flex-1 space-y-3 overflow-y-auto px-3 py-4"
          >
            <PaginationSentinel
              hasNext={messagePages.hasNextPage}
              onVisible={loadOlderMessages}
            />

            {messagePages.isPending ? (
              <div className="grid place-items-center py-10">
                <Spinner className="h-5 w-5" />
              </div>
            ) : null}

            {!messagePages.isPending && messages.length === 0 ? (
              <div className="grid place-items-center py-14 text-center">
                <p className="text-sm text-slate-400">No messages yet — say hi.</p>
              </div>
            ) : null}

            {messages.map((message, index) => {
              const previous = messages[index - 1]
              const showDate = previous === undefined || !sameDay(previous.created_at, message.created_at)
              return (
                <div key={messageKey(message)}>
                  {showDate ? (
                    <p className="my-3 text-center text-[11px] font-medium text-slate-500">
                      {formatDay(message.created_at)}
                    </p>
                  ) : null}
                  <MessageBubble
                    message={message}
                    mine={(message.sender?.id ?? 0) === me?.id}
                    showSender={conversation.type === 'group' && (message.sender?.id ?? 0) !== me?.id}
                    actionable={message.id > 0}
                    canDelete={
                      message.id > 0 &&
                      ((message.sender?.id ?? 0) === me?.id ||
                        (conversation.type === 'group' && isModerator))
                    }
                    readReceipts={
                      message.id === myReadWatermarkId ? (message.read_by ?? []) : []
                    }
                    onReact={(reaction) =>
                      reactMutation.mutate({ messageId: message.id, reaction })
                    }
                    onDelete={() => {
                      if (window.confirm('Delete this message for everyone?')) {
                        deleteMutation.mutate(message.id)
                      }
                    }}
                  />
                </div>
              )
            })}
          </div>

          <form
            onSubmit={(event) => {
              event.preventDefault()
              handleSend()
            }}
            className="flex items-end gap-2 border-t border-white/5 p-3"
          >
            <textarea
              value={draft}
              onChange={(event) => {
                setDraft(event.target.value)
                maybeSendTyping()
              }}
              onKeyDown={(event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                  event.preventDefault()
                  handleSend()
                }
              }}
              placeholder="Type a message…"
              rows={1}
              aria-label="Message"
              className="min-h-[2.5rem] max-h-32 flex-1 resize-none rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
            />
            <button
              type="submit"
              disabled={!draft.trim() || sendMutation.isPending}
              aria-label="Send message"
              className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500 text-[#fff] transition hover:bg-brand-400 disabled:opacity-40"
            >
              {sendMutation.isPending ? (
                <Spinner className="h-4 w-4" />
              ) : (
                <SendIcon className="h-5 w-5" />
              )}
            </button>
          </form>
          {composerError ? (
            <p className="border-t border-white/5 px-4 pb-2 text-xs text-rose-400">
              {composerError}
            </p>
          ) : null}
        </>
      ) : null}

      {threadOpen && conversation !== null ? (
        <GroupThreadModal
          conversationId={conversation.id}
          groupName={name}
          onClose={() => setThreadOpen(false)}
        />
      ) : null}

      {inviteOpen && conversation !== null ? (
        <GroupInviteModal
          conversationId={conversation.id}
          groupName={name}
          onClose={() => setInviteOpen(false)}
        />
      ) : null}
    </section>
  )
}

function PaginationSentinel({
  hasNext,
  onVisible,
}: {
  hasNext: boolean
  onVisible: () => void
}) {
  const ref = useRef<HTMLDivElement>(null)
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const node = ref.current
    if (!node) return
    const observer = new IntersectionObserver(
      ([entry]) => setVisible(entry.isIntersecting),
      { root: null, rootMargin: '200px' },
    )
    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    if (visible && hasNext) onVisible()
  }, [visible, hasNext, onVisible])

  return <div ref={ref} aria-hidden="true" />
}

function MessageBubble({
  message,
  mine,
  showSender,
  actionable,
  canDelete,
  readReceipts,
  onReact,
  onDelete,
}: {
  message: ConversationMessage
  mine: boolean
  showSender: boolean
  actionable: boolean
  canDelete: boolean
  readReceipts: ReadReceipt[]
  onReact: (reaction: ReactionName) => void
  onDelete: () => void
}) {
  const kind: MessageKind = message.type ?? 'text'
  const isMedia = kind === 'image' || kind === 'video'
  const [picker, setPicker] = useState(false)

  return (
    <div className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
      <div className={`flex max-w-[80%] flex-col ${mine ? 'items-end' : 'items-start'}`}>
        {showSender ? (
          <p className="mb-1 ml-1 text-[11px] font-medium text-slate-500">
            {message.sender?.display_name ?? message.sender?.username ?? 'Member'}
          </p>
        ) : null}
        <div
          className={[
            'rounded-2xl px-3 py-2 text-sm leading-relaxed',
            mine
              ? 'rounded-br-md bg-brand-500/90 text-[#fff]'
              : 'rounded-bl-md bg-white/[0.06] text-slate-100',
            isMedia ? 'bg-transparent p-0' : '',
          ].join(' ')}
        >
          {isMedia && message.media_url ? (
            kind === 'video' ? (
              <video src={message.media_url} controls playsInline className="max-h-72 rounded-2xl" />
            ) : (
              <img src={message.media_url} alt="Shared media" className="max-h-72 w-full rounded-2xl object-cover" />
            )
          ) : (
            <p className="whitespace-pre-wrap break-words">{message.body ?? ''}</p>
          )}
          <div
            className={[
              'mt-1 flex items-center justify-end gap-1',
              isMedia ? 'pr-2 pb-1 text-[10px] text-slate-200' : 'text-[10px] text-slate-400',
              mine ? 'text-slate-300' : '',
            ].join(' ')}
          >
            <span>{clockTime(message.created_at)}</span>
            {mine ? (
              readReceipts.length > 0 ? (
                <span
                  className="flex -space-x-1.5"
                  title={`Seen by ${readReceipts.map((reader) => reader.display_name).join(', ')}`}
                >
                  {readReceipts.slice(0, 3).map((reader) =>
                    reader.avatar_url ? (
                      <img
                        key={reader.id}
                        src={reader.avatar_url}
                        alt=""
                        className="h-4 w-4 rounded-full border border-white/40 object-cover"
                      />
                    ) : (
                      <span
                        key={reader.id}
                        className="grid h-4 w-4 place-items-center rounded-full border border-white/40 bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[7px] font-bold text-[#fff]"
                      >
                        {(reader.display_name || reader.username).charAt(0).toUpperCase()}
                      </span>
                    ),
                  )}
                  {readReceipts.length > 3 ? (
                    <span className="grid h-4 w-4 place-items-center rounded-full border border-white/40 bg-white/15 text-[7px] font-bold text-slate-200">
                      +{readReceipts.length - 3}
                    </span>
                  ) : null}
                </span>
              ) : (
                <span className="text-slate-400" title="Sent">
                  ✓
                </span>
              )
            ) : null}
          </div>
        </div>
        {actionable ? (
          <div className="mt-1 flex flex-wrap items-center gap-1">
            {REACTIONS.filter((reaction) => (message.reactions?.[reaction] ?? 0) > 0).map(
              (reaction) => {
                const count = message.reactions?.[reaction] ?? 0
                const mineReacted = message.my_reaction === reaction
                return (
                  <button
                    key={reaction}
                    type="button"
                    onClick={() => onReact(reaction)}
                    title={`${reaction}${mineReacted ? ' (added)' : ''}`}
                    aria-label={`React ${reaction}`}
                    className={[
                      'flex items-center gap-1 rounded-full border border-white/10 px-1.5 py-0.5 text-xs transition',
                      mineReacted
                        ? 'bg-brand-500/20 ring-1 ring-brand-400/50'
                        : 'bg-white/[0.03] hover:bg-white/10',
                    ].join(' ')}
                  >
                    {REACTION_EMOJI[reaction]}
                    <span className="text-[10px] font-semibold text-slate-300">{count}</span>
                  </button>
                )
              },
            )}
            <div className="flex items-center gap-1">
              <button
                type="button"
                onClick={() => setPicker((current) => !current)}
                aria-label={picker ? 'Close reactions' : 'Add reaction'}
                title="Add reaction"
                className="grid h-6 w-6 place-items-center rounded-full bg-white/5 text-xs transition hover:bg-white/10"
              >
                😀
              </button>
              {canDelete ? (
                <button
                  type="button"
                  onClick={onDelete}
                  aria-label="Delete message"
                  title="Delete for everyone"
                  className="grid h-6 w-6 place-items-center rounded-full bg-white/5 text-[10px] transition hover:bg-rose-500/20 hover:text-rose-300"
                >
                  🗑
                </button>
              ) : null}
            </div>
          </div>
        ) : null}
        {picker ? (
          <div className="mt-1 flex gap-1 rounded-full border border-white/10 bg-midnight-900 p-1">
            {REACTIONS.map((reaction) => (
              <button
                key={reaction}
                type="button"
                onClick={() => {
                  onReact(reaction)
                  setPicker(false)
                }}
                aria-label={reaction}
                title={reaction}
                className="grid h-7 w-7 place-items-center rounded-full text-sm transition hover:scale-110 hover:bg-white/10"
              >
                {REACTION_EMOJI[reaction]}
              </button>
            ))}
          </div>
        ) : null}
      </div>
    </div>
  )
}

function NewChatModal({
  mode,
  onClose,
  onCreated,
}: {
  mode: 'dm' | 'group'
  onClose: () => void
  onCreated: (conversation: Conversation) => void
}) {
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const [active, setActive] = useState<'dm' | 'group'>(mode)
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<User[]>([])
  const [selected, setSelected] = useState<number[]>([])
  const [groupName, setGroupName] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const handle = window.setTimeout(() => {
      const trimmed = query.trim()
      if (!trimmed) {
        setResults([])
        return
      }
      void usersApi
        .search(trimmed)
        .then((response) =>
          setResults(response.users.filter((user) => user.id !== me?.id)),
        )
        .catch(() => setResults([]))
    }, 250)
    return () => window.clearTimeout(handle)
  }, [query, me?.id])

  const create = useMutation({
    mutationFn: async () => {
      if (active === 'dm') {
        const target = selected[0]
        if (!target) throw new Error('Pick someone to message first.')
        return (await chatApi.startDm(target)).conversation
      }
      if (selected.length === 0) throw new Error('Add at least one member to the group.')
      const name = groupName.trim()
      if (!name) throw new Error('Give the group a name.')
      return (await chatApi.createGroup(name, selected)).conversation
    },
    onSuccess: (conversation) => {
      void queryClient.invalidateQueries({ queryKey: ['chat'] })
      onCreated(conversation)
    },
    onError: (err) => setError(err.message),
  })

  function toggle(userId: number) {
    setSelected((current) => {
      if (active === 'dm') return [userId]
      return current.includes(userId)
        ? current.filter((id) => id !== userId)
        : [...current, userId]
    })
  }

  function submit() {
    setError(null)
    create.mutate()
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-3xl glass-card p-4">
        <div className="flex items-center justify-between">
          <h2 className="text-base font-bold text-white">New chat</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
          >
            <XIcon className="h-5 w-5" />
          </button>
        </div>

        <div className="mt-3 flex gap-1 rounded-xl bg-white/5 p-1">
          {(['dm', 'group'] as const).map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => {
                setActive(tab)
                setSelected([])
                setError(null)
              }}
              className={[
                'flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                active === tab
                  ? 'bg-brand-500 text-[#fff]'
                  : 'text-slate-400 hover:text-slate-200',
              ].join(' ')}
            >
              {tab === 'dm' ? 'Direct message' : 'Group'}
            </button>
          ))}
        </div>

        {active === 'group' ? (
          <input
            value={groupName}
            onChange={(event) => setGroupName(event.target.value)}
            placeholder="Group name"
            aria-label="Group name"
            className="mt-3 w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
          />
        ) : null}

        <input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Search people…"
          aria-label="Search people"
          className="mt-3 w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
        />

        <div className="mt-3 max-h-56 overflow-y-auto space-y-0.5">
          {results.length === 0 && query.trim() ? (
            <p className="px-2 py-4 text-center text-xs text-slate-500">No people found.</p>
          ) : null}
          {results.map((user) => {
            const isSelected = selected.includes(user.id)
            return (
              <button
                key={user.id}
                type="button"
                onClick={() => toggle(user.id)}
                className={[
                  'flex w-full items-center gap-3 rounded-xl p-2 text-left transition',
                  isSelected ? 'bg-brand-500/15' : 'hover:bg-white/5',
                ].join(' ')}
              >
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-[#fff]">
                  {(user.display_name || user.username).charAt(0).toUpperCase()}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-slate-100">
                    {user.display_name || user.username}
                  </p>
                  <p className="truncate text-xs text-slate-500">@{user.username}</p>
                </div>
                {isSelected ? (
                  <span className="grid h-5 w-5 place-items-center rounded-full bg-brand-500 text-[10px] text-[#fff]">
                    ✓
                  </span>
                ) : null}
              </button>
            )
          })}
        </div>

        <button
          type="button"
          onClick={submit}
          disabled={create.isPending}
          className="btn-primary mt-4 w-full"
        >
          {create.isPending ? <Spinner className="h-4 w-4" /> : null}
          {create.isPending
            ? 'Creating…'
            : active === 'dm'
              ? selected.length > 0
                ? 'Message'
                : 'Pick someone'
              : 'Create group'}
        </button>
        {error ? <p className="mt-2 text-xs text-rose-400">{error}</p> : null}
      </div>
    </div>
  )
}

function buildChronological(
  pages: { messages: ConversationMessage[]; next_cursor: string | null }[],
): ConversationMessage[] {
  return [...pages]
    .reverse()
    .flatMap((page) => [...page.messages].reverse())
}

function mutateMessageInPages(
  current: InfiniteData<ChatMessagesPage, string | undefined> | undefined,
  messageId: number,
  change: (message: ConversationMessage) => ConversationMessage,
): InfiniteData<ChatMessagesPage, string | undefined> | undefined {
  if (current === undefined) return current
  return {
    ...current,
    pages: current.pages.map((page) => ({
      ...page,
      messages: page.messages.map((message) =>
        message.id === messageId ? change(message) : message,
      ),
    })),
    pageParams: current.pageParams,
  }
}

function displayName(conversation: Conversation): string {
  return conversation.display_name || 'Chat'
}

function previewFor(conversation: Conversation): string {
  const last = conversation.last_message
  if (!last) return 'No messages yet'
  if (last.body) return last.body
  if (last.type === 'image') return 'Photo'
  if (last.type === 'video') return 'Video'
  return 'Message'
}

function messageKey(message: ConversationMessage): string {
  return message.client_id ? `temp-${message.client_id}` : `m-${message.id}`
}

function sameDay(a: string, b: string): boolean {
  const da = new Date(a)
  const db = new Date(b)
  return (
    da.getFullYear() === db.getFullYear() &&
    da.getMonth() === db.getMonth() &&
    da.getDate() === db.getDate()
  )
}

function formatDay(value: string): string {
  const date = new Date(value)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  if (sameDay(value, today.toISOString())) return 'Today'
  if (sameDay(value, yesterday.toISOString())) return 'Yesterday'
  return date.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })
}

function clockTime(value: string): string {
  return new Date(value).toLocaleTimeString(undefined, {
    hour: 'numeric',
    minute: '2-digit',
  })
}

function relativeTime(value: string): string {
  const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000)
  if (seconds < 60) return 'now'
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h`
  const days = Math.round(hours / 24)
  if (days < 7) return `${days}d`
  return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}