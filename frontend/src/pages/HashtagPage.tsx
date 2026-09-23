import { useInfiniteQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import PostCard from '@/components/PostCard'
import { hashtagsApi } from '@/lib/api'

export default function HashtagPage() {
  const { tag = '' } = useParams<{ tag: string }>()
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)

  const feed = useInfiniteQuery({
    queryKey: ['hashtags', 'page', tag],
    queryFn: ({ pageParam }) => hashtagsApi.page(tag, pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
    enabled: tag.length > 0,
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

  const posts = feedPages?.pages.flatMap((page) => page.posts) ?? []
  const hashtag = feedPages?.pages[0]?.hashtag
  const notFound = feed.isError

  return (
    <div className="mx-auto w-full max-w-3xl space-y-4">
      <header className="glass-card p-4">
        {notFound ? (
          <>
            <h1 className="text-xl font-extrabold tracking-tight text-white">#{tag}</h1>
            <p className="mt-1 text-sm text-slate-400">
              This hashtag has no posts yet — be the first to use it.
            </p>
          </>
        ) : (
          <>
            <h1 className="text-xl font-extrabold tracking-tight text-slate-200">
              <span className="text-amber-300">#{tag}</span>
            </h1>
            <p className="mt-1 text-sm text-slate-400">
              {hashtag ? (
                <>
                  {hashtag.posts_count.toLocaleString()} post
                  {hashtag.posts_count === 1 ? '' : 's'} tagged with #{hashtag.name}
                </>
              ) : (
                'Loading…'
              )}
            </p>
          </>
        )}
      </header>

      <div className="space-y-3">
        {posts.map((post) => (
          <PostCard key={post.id} post={post} cacheKey={['hashtags', 'page', tag]} />
        ))}

        {isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : null}

        {!isPending && !notFound && posts.length === 0 ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">No posts yet</p>
            <p className="mt-1 text-sm text-slate-400">Be the first to post with #{tag}.</p>
          </div>
        ) : null}

        <div ref={loadMoreRef} aria-hidden="true" />
      </div>
    </div>
  )
}