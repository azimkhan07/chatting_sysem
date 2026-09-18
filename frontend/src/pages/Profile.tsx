import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { postsApi, usersApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { Post } from '@/types/post'

export default function Profile() {
  const user = useAuthStore((state) => state.user)
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)

  const profileQuery = useQuery({
    queryKey: ['user', 'profile', user?.username],
    queryFn: () => usersApi.get(user!.username),
    enabled: user !== null,
  })

  const counts = profileQuery.data?.user

  const feed = useInfiniteQuery({
    queryKey: ['posts', 'mine'],
    queryFn: ({ pageParam }) => postsApi.mine(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  })

  const {
    data: feedPages,
    isPending,
    isFetching,
    isFetchingNextPage,
    fetchNextPage,
    hasNextPage,
  } = feed

  useEffect(() => {
    if (loadMoreVisible && hasNextPage && !isFetchingNextPage && !isFetching) {
      void fetchNextPage()
    }
  }, [loadMoreVisible, hasNextPage, isFetchingNextPage, isFetching, fetchNextPage])

  useEffect(() => {
    const node = loadMoreRef.current
    if (!node) return

    const observer = new IntersectionObserver(
      ([entry]) => setLoadMoreVisible(entry.isIntersecting),
      { root: null, rootMargin: '200px' },
    )
    observer.observe(node)

    return () => observer.disconnect()
  }, [])

  const posts = feedPages?.pages.flatMap((page) => page.posts) ?? []
  const initials = (user?.display_name ?? '?').charAt(0).toUpperCase()
  const joined = user?.created_at
    ? new Date(user.created_at).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
    : ''

  return (
    <div className="space-y-4">
      <header className="glass-card p-4 sm:p-5">
        <div className="flex items-center gap-4">
          <div className="relative shrink-0">
            <span className="grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xl font-bold text-[#fff] ring-2 ring-white/10 sm:h-20 sm:w-20 sm:text-2xl">
              {initials}
            </span>
            {user?.is_verified ? (
              <span className="absolute -right-1 -bottom-1 grid h-6 w-6 place-items-center rounded-full bg-sky-500 text-xs font-bold text-[#fff] ring-2 ring-midnight-950">
                ✓
              </span>
            ) : null}
          </div>

          <div className="min-w-0">
            <h1 className="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-white">
              {user?.display_name ?? '…'}
              {user?.is_verified ? (
                <span className="badge bg-sky-500/15 text-sm text-sky-300">✓</span>
              ) : null}
            </h1>
            <p className="text-sm text-slate-400">@{user?.username ?? '…'}</p>
            {joined ? <p className="mt-0.5 text-xs text-slate-500">Joined {joined}</p> : null}
          </div>
        </div>

        <div className="mt-4 grid grid-cols-3 gap-2 border-t border-white/5 pt-4 text-center">
          <div>
            <p className="text-lg font-extrabold text-white">
              {(counts?.posts_count ?? posts.length).toLocaleString()}
            </p>
            <p className="text-xs text-slate-500">Posts</p>
          </div>
          <div>
            <p className="text-lg font-extrabold text-white">
              {(counts?.followers_count ?? 0).toLocaleString()}
            </p>
            <p className="text-xs text-slate-500">Followers</p>
          </div>
          <div>
            <p className="text-lg font-extrabold text-white">
              {(counts?.following_count ?? 0).toLocaleString()}
            </p>
            <p className="text-xs text-slate-500">Following</p>
          </div>
        </div>

        <button
          type="button"
          disabled
          title="Coming soon"
          className="btn-secondary mt-4 w-full"
        >
          Edit profile
        </button>
      </header>

      <div className="space-y-1">
        <h2 className="px-1 text-sm font-bold text-slate-300">Your posts</h2>

        {isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!isPending && posts.length === 0 ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">Nothing here yet</p>
            <p className="mt-1 text-sm text-slate-400">
              Your posts will show up in this grid.
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

export function ProfileTile({ post }: { post: Post }) {
  const image = post.media.find((item) => item.type === 'image')
  const video = post.media.find((item) => item.type === 'video')

  return (
    <div className="group relative aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10">
      {image ? (
        <img
          src={image.url}
          alt=""
          loading="lazy"
          className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
        />
      ) : video ? (
        <video
          src={video.url}
          muted
          playsInline
          preload="metadata"
          className="h-full w-full object-cover"
        />
      ) : (
        <div className="flex h-full w-full items-end bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 p-2">
          <p className="line-clamp-2 text-[11px] leading-tight text-slate-300">
            {post.body}
          </p>
        </div>
      )}
      {post.body ? (
        <span className="absolute inset-x-0 bottom-0 hidden bg-gradient-to-t from-black/70 to-transparent p-1.5 group-hover:block">
          <p className="truncate text-[10px] text-[#fff]">{post.body}</p>
        </span>
      ) : null}
    </div>
  )
}