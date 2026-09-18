import { useInfiniteQuery, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { notificationsApi } from '@/lib/api'
import { path, userProfile } from '@/lib/paths'
import type { Notification } from '@/types/notification'
import type { User } from '@/types/user'

export default function Notifications() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)

  const feed = useInfiniteQuery({
    queryKey: ['notifications'],
    queryFn: ({ pageParam }) => notificationsApi.list(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  })

  const { data, isPending, fetchNextPage, hasNextPage, isFetchingNextPage } = feed
  const items = data?.pages.flatMap((page) => page.notifications) ?? []

  const unreadQuery = useQuery({
    queryKey: ['notifications', 'unread'],
    queryFn: notificationsApi.unreadCount,
    refetchInterval: 30_000,
  })

  useEffect(() => {
    if (!items.length) return
    void notificationsApi.markAllRead().then(() => {
      void queryClient.invalidateQueries({ queryKey: ['notifications', 'unread'] })
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })
    })
  }, [items.length, queryClient])

  useEffect(() => {
    const node = loadMoreRef.current
    if (!node) return
    const observer = new IntersectionObserver(
      ([entry]) => setLoadMoreVisible(entry.isIntersecting),
      { root: null, rootMargin: '300px' },
    )
    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    if (loadMoreVisible && hasNextPage && !isFetchingNextPage) {
      void fetchNextPage()
    }
  }, [loadMoreVisible, hasNextPage, isFetchingNextPage, fetchNextPage])

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-extrabold tracking-tight text-white">Activity</h1>
        {unreadQuery.data?.unread ? (
          <span className="badge bg-brand-500/15 text-xs text-brand-300">
            {unreadQuery.data.unread} new
          </span>
        ) : null}
      </div>

      {isPending ? (
        <div className="grid place-items-center py-16">
          <Spinner className="h-6 w-6" />
        </div>
      ) : null}

      {!isPending && items.length === 0 ? (
        <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-16 text-center">
          <p className="text-base font-semibold text-slate-200">You're all caught up</p>
          <p className="mt-1 text-sm text-slate-400">
            Follows, likes and comments will show up here.
          </p>
        </div>
      ) : null}

      {items.length > 0 ? (
        <div className="glass-card divide-y divide-white/5">
          {items.map((notification) => (
            <NotificationRow
              key={notification.id}
              notification={notification}
              onOpen={() => {
                if (notification.type === 'follow') {
                  navigate(userProfile(notification.actor.username))
                } else {
                  navigate(path('home'))
                }
              }}
            />
          ))}
        </div>
      ) : null}

      <div ref={loadMoreRef} aria-hidden="true" />
    </div>
  )
}

interface NotificationRowProps {
  notification: Notification
  onOpen: () => void
}

function NotificationRow({ notification, onOpen }: NotificationRowProps) {
  const actor = notification.actor

  return (
    <button
      type="button"
      onClick={onOpen}
      className="flex w-full items-center gap-3 p-3 text-left transition hover:bg-white/[0.03]"
    >
      <Avatar user={actor} />
      <div className="min-w-0 flex-1">
        <p className="text-sm leading-snug text-slate-300">
          <span className="font-semibold text-white">
            {actor.display_name || actor.username}
          </span>{' '}
          {messageFor(notification)}
        </p>
        <p className="mt-0.5 text-xs text-slate-500">{relativeTime(notification.created_at)}</p>
      </div>
      {notification.read_at === null ? (
        <span className="h-2 w-2 shrink-0 rounded-full bg-brand-400" />
      ) : null}
    </button>
  )
}

function Avatar({ user }: { user: User }) {
  return (
    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-white">
      {(user.display_name || user.username).charAt(0).toUpperCase()}
    </span>
  )
}

function messageFor(notification: Notification): string {
  switch (notification.type) {
    case 'follow':
      return 'started following you.'
    case 'like':
      return 'liked your post.'
    case 'comment':
      return `commented: “${notification.data.comment_preview ?? ''}”`
  }
}

function relativeTime(value: string): string {
  const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.round(hours / 24)
  if (days < 7) return `${days}d ago`
  return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}