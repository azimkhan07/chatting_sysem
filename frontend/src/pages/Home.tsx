import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { postsApi } from '@/lib/api'
import { timeAgo } from '@/lib/time'
import { useAuthStore } from '@/stores/authStore'
import type { Post } from '@/types/post'

const MAX_POST_LENGTH = 5000

export default function Home() {
  const sessionUser = useAuthStore((state) => state.user)
  const queryClient = useQueryClient()
  const [body, setBody] = useState('')
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)

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

  const feed = useInfiniteQuery({
    queryKey: ['posts', 'feed'],
    queryFn: ({ pageParam }) => postsApi.list(pageParam),
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
  }, [
    loadMoreVisible,
    hasNextPage,
    isFetchingNextPage,
    isFetching,
    fetchNextPage,
  ])

  const createPost = useMutation({
    mutationFn: (text: string) => postsApi.create(text),
    onSuccess: (result) => {
      queryClient.setQueryData<{
        pages: { posts: Post[]; next_cursor: string | null }[]
      }>(['posts', 'feed'], (current) => {
        if (!current) return current
        return {
          ...current,
          pages: current.pages.map((page, index) =>
            index === 0
              ? { ...page, posts: [result.post, ...page.posts] }
              : page,
          ),
        }
      })
      setBody('')
    },
  })

  const posts = feedPages?.pages.flatMap((page) => page.posts) ?? []

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    const text = body.trim()
    if (!text || text.length > MAX_POST_LENGTH || createPost.isPending) return
    createPost.mutate(text)
  }

  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <h1 className="text-2xl font-extrabold tracking-tight text-white">
          Home
        </h1>
        <p className="text-sm text-slate-400">
          What's on your mind, {sessionUser?.display_name.split(' ')[0] ?? 'friend'}?
        </p>
      </header>

      <motion.form
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: 'easeOut' }}
        onSubmit={handleSubmit}
        className="glass-card p-4"
      >
        <div className="flex gap-3">
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-white">
            {(sessionUser?.display_name ?? '?').charAt(0).toUpperCase()}
          </span>
          <textarea
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder="Share something with your circle…"
            rows={3}
            className="min-h-[4.5rem] w-full resize-none bg-transparent text-sm text-slate-100 placeholder:text-slate-500 outline-none"
            aria-label="Post body"
          />
        </div>
        <div className="mt-2 flex items-center justify-between border-t border-white/5 pt-3">
          <p
            className={`text-xs ${
              body.length > MAX_POST_LENGTH ? 'text-rose-400' : 'text-slate-500'
            }`}
          >
            {body.length.toLocaleString()} / {MAX_POST_LENGTH.toLocaleString()}
          </p>
          <button
            type="submit"
            disabled={
              !body.trim() || body.length > MAX_POST_LENGTH || createPost.isPending
            }
            className="btn-primary w-auto px-4 py-2"
          >
            {createPost.isPending ? <Spinner className="h-4 w-4" /> : null}
            {createPost.isPending ? 'Posting…' : 'Post'}
          </button>
        </div>
      </motion.form>

      <div className="space-y-3">
        {posts.map((post) => (
          <PostCard key={post.id} post={post} />
        ))}

        {isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!isPending && posts.length === 0 ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">
              No posts yet
            </p>
            <p className="mt-1 text-sm text-slate-400">
              Be the first — share something with your circle above.
            </p>
          </div>
        ) : null}

        <div ref={loadMoreRef} aria-hidden="true" />
      </div>
    </div>
  )
}

function PostCard({ post }: { post: Post }) {
  const initials = post.author.display_name.charAt(0).toUpperCase()

  return (
    <motion.article
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, ease: 'easeOut' }}
      className="glass-card p-4"
    >
      <div className="flex gap-3">
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500/30 to-fuchsia-500/30 text-sm font-bold text-brand-200">
          {initials}
        </span>
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-baseline gap-x-2">
            <p className="text-sm font-semibold text-slate-200">
              {post.author.display_name}
            </p>
            {post.author.is_verified ? (
              <span className="badge bg-sky-500/15 text-sky-300">✓</span>
            ) : null}
            <p className="text-xs text-slate-500">
              @{post.author.username} · {timeAgo(post.created_at)}
            </p>
          </div>
          <p className="mt-1.5 text-sm leading-relaxed whitespace-pre-wrap text-slate-200">
            {post.body}
          </p>
        </div>
      </div>
    </motion.article>
  )
}