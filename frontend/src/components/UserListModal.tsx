import { useInfiniteQuery } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import FollowButton from '@/components/FollowButton'
import { usersApi } from '@/lib/api'
import { userProfile } from '@/lib/paths'
import { useAuthStore } from '@/stores/authStore'
import type { User } from '@/types/user'

type ConnectionKind = 'followers' | 'following'

interface UserListModalProps {
  identifier: string | number
  kind: ConnectionKind
  onClose: () => void
}

export default function UserListModal({
  identifier,
  kind,
  onClose,
}: UserListModalProps) {
  const navigate = useNavigate()
  const me = useAuthStore((state) => state.user)
  const loadMoreRef = useRef<HTMLDivElement>(null)

  const list = useInfiniteQuery({
    queryKey: ['users', kind, identifier],
    queryFn: ({ pageParam }) =>
      kind === 'followers'
        ? usersApi.followers(identifier, pageParam)
        : usersApi.following(identifier, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  })

  const users = list.data?.pages.flatMap((page) => page.users) ?? []

  useEffect(() => {
    const node = loadMoreRef.current
    if (!node) return
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && list.hasNextPage && !list.isFetchingNextPage) {
          void list.fetchNextPage()
        }
      },
      { rootMargin: '200px' },
    )
    observer.observe(node)
    return () => observer.disconnect()
  }, [list])

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [onClose])

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 backdrop-blur-sm sm:items-center sm:p-4"
      onClick={onClose}
      role="presentation"
    >
      <motion.div
        initial={{ opacity: 0, y: 24 }}
        animate={{ opacity: 1, y: 0 }}
        exit={{ opacity: 0, y: 24 }}
        transition={{ duration: 0.2, ease: 'easeOut' }}
        onClick={(event) => event.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={kind === 'followers' ? 'Followers' : 'Following'}
        className="max-h-[80vh] w-full max-w-md overflow-hidden rounded-t-3xl border border-white/10 bg-midnight-900/95 sm:rounded-3xl"
      >
        <div className="flex items-center justify-between border-b border-white/10 px-4 py-3">
          <h2 className="text-sm font-bold text-slate-200">
            {kind === 'followers' ? 'Followers' : 'Following'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="btn-quiet px-2.5 py-1 text-[11px]"
            aria-label="Close"
          >
            Close
          </button>
        </div>

        <div className="max-h-[65vh] space-y-1 overflow-y-auto p-2">
          {list.isPending ? (
            <div className="grid place-items-center py-12">
              <Spinner className="h-6 w-6" />
            </div>
          ) : null}

          {!list.isPending && users.length === 0 ? (
            <p className="px-3 py-10 text-center text-sm text-slate-400">
              {kind === 'followers' ? 'No followers yet.' : 'Not following anyone yet.'}
            </p>
          ) : null}

          {users.map((user) => (
            <UserRow
              key={user.id}
              user={user}
              isSelf={me?.id === user.id}
              onOpen={() => {
                onClose()
                navigate(userProfile(user.username))
              }}
            />
          ))}

          {list.isFetchingNextPage ? (
            <div className="grid place-items-center py-4">
              <Spinner className="h-5 w-5" />
            </div>
          ) : null}

          <div ref={loadMoreRef} aria-hidden="true" />
        </div>
      </motion.div>
    </div>
  )
}

function UserRow({
  user,
  isSelf,
  onOpen,
}: {
  user: User
  isSelf: boolean
  onOpen: () => void
}) {
  return (
    <div className="flex items-center gap-3 rounded-2xl px-3 py-2 transition hover:bg-white/5">
      <button
        type="button"
        onClick={onOpen}
        className="flex min-w-0 flex-1 items-center gap-3 text-left"
      >
        {user.avatar_url ? (
          <img
            src={user.avatar_url}
            alt=""
            className="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-white/10"
          />
        ) : (
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-[#fff]">
            {(user.display_name || user.username).charAt(0).toUpperCase()}
          </span>
        )}
        <span className="min-w-0">
          <span className="flex items-center gap-1.5">
            <span className="truncate text-sm font-semibold text-slate-100">
              {user.display_name || user.username}
            </span>
            {user.is_verified ? (
              <span className="badge bg-sky-500/15 text-[10px] text-sky-300">✓</span>
            ) : null}
          </span>
          <span className="block truncate text-xs text-slate-500">@{user.username}</span>
        </span>
      </button>

      {isSelf ? null : (
        <div className="w-24 shrink-0">
          <FollowButton user={user} />
        </div>
      )}
    </div>
  )
}
