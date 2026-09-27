import { useInfiniteQuery } from '@tanstack/react-query'
import { AnimatePresence } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import PostCard from '@/components/PostCard'
import PostComposerModal from '@/components/PostComposerModal'
import ReelCard from '@/components/ReelCard'
import StoriesRow from '@/components/StoriesRow'
import { postsApi } from '@/lib/api'

type Tab = 'posts' | 'reels'

export default function Home() {
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)
  const [composerOpen, setComposerOpen] = useState(false)
  const [tab, setTab] = useState<Tab>('posts')

  const posts = useInfiniteQuery({
    queryKey: ['posts', 'feed'],
    queryFn: ({ pageParam }) => postsApi.list(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
    enabled: tab === 'posts',
  })

  const reels = useInfiniteQuery({
    queryKey: ['posts', 'reels'],
    queryFn: ({ pageParam }) => postsApi.reels(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
    enabled: tab === 'reels',
  })

  const active = tab === 'posts' ? posts : reels
  const {
    data: pages,
    isPending,
    isFetching,
    isFetchingNextPage,
    fetchNextPage,
    hasNextPage,
  } = active

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

  const items = pages?.pages.flatMap((page) => page.posts) ?? []

  return (
    <div className="space-y-4">
      <StoriesRow />

      <div className="flex items-center justify-between gap-2 px-1">
        <div className="flex items-center gap-1 rounded-full bg-white/5 p-1">
          <TabButton active={tab === 'posts'} onClick={() => setTab('posts')}>
            Feed
          </TabButton>
          <TabButton active={tab === 'reels'} onClick={() => setTab('reels')}>
            Reels
          </TabButton>
        </div>
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
        {tab === 'posts'
          ? items.map((post) => <PostCard key={post.id} post={post} cacheKey={['posts', 'feed']} />)
          : items.map((post) => <ReelCard key={post.id} post={post} />)}

        {isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!isPending && items.length === 0 ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">
              {tab === 'reels' ? 'No reels yet' : 'Your feed is empty'}
            </p>
            <p className="mt-1 text-sm text-slate-400">
              {tab === 'reels'
                ? 'Upload a video — it will show up here as a reel.'
                : 'Follow people or share a post to get started.'}
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

function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
        active ? 'bg-brand-500/20 text-brand-200' : 'text-slate-400 hover:text-slate-200'
      }`}
    >
      {children}
    </button>
  )
}

function PlusIcon({ className }: { className: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" className={className} aria-hidden="true">
      <path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </svg>
  )
}
