import { useInfiniteQuery, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import FollowButton from '@/components/FollowButton'
import { usersApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { ProfileTile } from '@/pages/Profile'

export default function UserProfile() {
  const { username = '' } = useParams<{ username: string }>()
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)

  const profileQuery = useQuery({
    queryKey: ['user', 'profile', username],
    queryFn: () => usersApi.get(username),
  })

  const feed = useInfiniteQuery({
    queryKey: ['posts', 'byuser', username],
    queryFn: ({ pageParam }) => usersApi.postsOf(username, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  })

  const {
    data: feedPages,
    isPending,
    isFetchingNextPage,
    fetchNextPage,
    hasNextPage,
  } = feed

  const posts = feedPages?.pages.flatMap((page) => page.posts) ?? []
  const user = profileQuery.data?.user

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

  if (profileQuery.isPending && user === undefined) {
    return (
      <div className="grid place-items-center py-16">
        <Spinner className="h-6 w-6" />
      </div>
    )
  }

  if (user === undefined) {
    return (
      <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-16 text-center">
        <p className="text-base font-semibold text-slate-200">User not found</p>
        <p className="mt-1 text-sm text-slate-400">
          This profile doesn't exist or may have been removed.
        </p>
      </div>
    )
  }

  const isMe = me?.id === user.id
  const initials = (user.display_name || user.username).charAt(0).toUpperCase()
  const joined = new Date(user.created_at).toLocaleDateString(undefined, {
    month: 'short',
    year: 'numeric',
  })

  return (
    <div className="space-y-4">
      <header className="glass-card p-4 sm:p-5">
        <div className="flex items-center gap-4">
          <div className="relative shrink-0">
            <span className="grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xl font-bold text-white ring-2 ring-white/10 sm:h-20 sm:w-20 sm:text-2xl">
              {initials}
            </span>
            {user.is_verified ? (
              <span className="absolute -right-1 -bottom-1 grid h-6 w-6 place-items-center rounded-full bg-sky-500 text-xs font-bold text-white ring-2 ring-midnight-950">
                ✓
              </span>
            ) : null}
          </div>

          <div className="min-w-0">
            <h1 className="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-white">
              {user.display_name || user.username}
              {user.is_verified ? (
                <span className="badge bg-sky-500/15 text-sm text-sky-300">✓</span>
              ) : null}
            </h1>
            <p className="text-sm text-slate-400">@{user.username}</p>
            <p className="mt-0.5 text-xs text-slate-500">Joined {joined}</p>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-3 gap-2 border-t border-white/5 pt-4 text-center">
          <div>
            <p className="text-lg font-extrabold text-white">
              {(user.posts_count ?? posts.length).toLocaleString()}
            </p>
            <p className="text-xs text-slate-500">Posts</p>
          </div>
          <div>
            <p className="text-lg font-extrabold text-white">
              {(user.followers_count ?? 0).toLocaleString()}
            </p>
            <p className="text-xs text-slate-500">Followers</p>
          </div>
          <div>
            <p className="text-lg font-extrabold text-white">
              {(user.following_count ?? 0).toLocaleString()}
            </p>
            <p className="text-xs text-slate-500">Following</p>
          </div>
        </div>

        {isMe ? (
          <button type="button" disabled className="btn-secondary mt-4 w-full">
            Edit profile
          </button>
        ) : (
          <div className="mt-4">
            <FollowButton
              user={user}
              onChanged={() => {
                void queryClient.invalidateQueries({ queryKey: ['user', 'profile'] })
              }}
            />
          </div>
        )}
      </header>

      <div className="space-y-1">
        <h2 className="px-1 text-sm font-bold text-slate-300">Posts</h2>

        {isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!isPending && posts.length === 0 ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">No posts yet</p>
            <p className="mt-1 text-sm text-slate-400">
              {user.display_name || user.username} hasn't posted anything yet.
            </p>
          </div>
        ) : null}

        {posts.length > 0 ? (
          <div className="grid grid-cols-3 gap-1.5">
            {posts.map((post) => (
              <ProfileTile key={post.id} post={post} />
            ))}
          </div>
        ) : null}

        <div ref={loadMoreRef} aria-hidden="true" />
      </div>
    </div>
  )
}