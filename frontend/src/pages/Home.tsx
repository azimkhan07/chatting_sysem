import { useInfiniteQuery } from '@tanstack/react-query'
import { AnimatePresence } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import PostComposerModal from '@/components/PostComposerModal'
import ReelCard from '@/components/ReelCard'
import StoriesRow from '@/components/StoriesRow'
import { postsApi } from '@/lib/api'

export default function Home() {
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)
  const [composerOpen, setComposerOpen] = useState(false)

  const feed = useInfiniteQuery({
    queryKey: ['posts', 'reels'],
    queryFn: ({ pageParam }) => postsApi.reels(pageParam),
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
    const node = loadMoreRef.current
    if (!node) return

    const observer = new IntersectionObserver(
      ([entry]) => setLoadMoreVisible(entry.isIntersecting),
      { root: null, rootMargin: '200px' },
    )
    observer.observe(node)

    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    if (loadMoreVisible && hasNextPage && !isFetchingNextPage && !isFetching) {
      void fetchNextPage()
    }
  }, [loadMoreVisible, hasNextPage, isFetchingNextPage, isFetching, fetchNextPage])

  const reels = feedPages?.pages.flatMap((page) => page.posts) ?? []

  return (
    <div className="space-y-4">
      <StoriesRow />

      <div className="flex items-center justify-between px-1">
        <h2 className="text-base font-bold text-slate-200">Reels</h2>
        <button
          type="button"
          onClick={() => setComposerOpen(true)}
          className="btn-quiet px-3 py-1.5 text-xs"
          aria-label="Create a post"
        >
          <PlusIcon className="h-4 w-4" />
          Create
        </button>
      </div>

      <div className="space-y-4 pb-20">
        {reels.map((post) => (
          <ReelCard key={post.id} post={post} />
        ))}

        {isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!isPending && reels.length === 0 ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">No reels yet</p>
            <p className="mt-1 text-sm text-slate-400">
              Upload a video — it will show up here as a reel.
            </p>
          </div>
        ) : null}

        <div ref={loadMoreRef} aria-hidden="true" />
      </div>

      <AnimatePresence>
        {composerOpen ? <PostComposerModal onClose={() => setComposerOpen(false)} /> : null}
      </AnimatePresence>
    </div>
  )
}

function PlusIcon({ className }: { className: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" className={className} aria-hidden="true">
      <path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </svg>
  )
}