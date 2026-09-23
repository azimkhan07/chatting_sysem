import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import PostCard from '@/components/PostCard'
import { hashtagsApi, postsApi, usersApi } from '@/lib/api'
import { hashtagPage, userProfile } from '@/lib/paths'
import type { HashtagSummary } from '@/lib/api'
import type { Post } from '@/types/post'
import type { User } from '@/types/user'

type Tab = 'people' | 'hashtags'
type GridMode = 'all' | 'trending'

export default function Explore() {
  const [query, setQuery] = useState('')
  const [tab, setTab] = useState<Tab>('people')
  const [gridMode, setGridMode] = useState<GridMode>('all')
  const [people, setPeople] = useState<User[]>([])
  const [hashtags, setHashtags] = useState<HashtagSummary[]>([])
  const [searching, setSearching] = useState(false)
  const [searchingFor, setSearchingFor] = useState('')
  const [selected, setSelected] = useState<Post | null>(null)
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [loadMoreVisible, setLoadMoreVisible] = useState(false)
  const debounceRef = useRef<number | null>(null)

  const grid = useInfiniteQuery({
    queryKey: ['posts', 'explore'],
    queryFn: ({ pageParam }) => postsApi.explore(pageParam),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  })

  const trending = useQuery({
    queryKey: ['posts', 'trending'],
    queryFn: () => postsApi.trending(),
    staleTime: 60_000,
  })

  const {
    data: gridPages,
    isPending,
    isFetching,
    isFetchingNextPage,
    fetchNextPage,
    hasNextPage,
  } = grid

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

  function runSearch(value: string) {
    const term = value.trim()
    if (debounceRef.current) window.clearTimeout(debounceRef.current)
    if (term.length < 2) {
      setSearchingFor('')
      setPeople([])
      setHashtags([])
      return
    }
    setSearchingFor(term)
    setSearching(true)
    debounceRef.current = window.setTimeout(() => {
      const tasks = [
        usersApi.search(term).then((data) => setPeople(data.users)).catch(() => setPeople([])),
        hashtagsApi.search(term).then((data) => setHashtags(data.hashtags)).catch(() => setHashtags([])),
      ]
      void Promise.all(tasks).finally(() => setSearching(false))
    }, 250)
  }

  useEffect(() => () => {
    if (debounceRef.current) window.clearTimeout(debounceRef.current)
  }, [])

  const posts = gridPages?.pages.flatMap((page) => page.posts) ?? []
  const trendingPosts = trending.data?.posts ?? []

  return (
    <div className="space-y-4">
      <header className="space-y-3">
        <h1 className="text-2xl font-extrabold tracking-tight text-white">Explore</h1>

        <div className="relative">
          <input
            value={query}
            onChange={(event) => {
              const value = event.target.value
              setQuery(value)
              runSearch(value)
            }}
            placeholder="Search people and #hashtags…"
            className="input-field"
            aria-label="Search"
          />
          {searching && searchingFor ? (
            <span className="absolute top-1/2 right-3 -translate-y-1/2">
              <Spinner className="h-4 w-4" />
            </span>
          ) : null}
        </div>

        <div className="flex items-center gap-1">
          {(['people', 'hashtags'] as Tab[]).map((item) => (
            <button
              key={item}
              type="button"
              onClick={() => setTab(item)}
              className={`rounded-full px-3 py-1.5 text-xs font-semibold capitalize transition ${
                tab === item
                  ? 'bg-brand-500/25 text-brand-200 ring-1 ring-brand-400/50'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              {item}
            </button>
          ))}
        </div>

        {searchingFor ? (
          <SearchResults
            tab={tab}
            people={people}
            hashtags={hashtags}
            empty={!searching && people.length === 0 && hashtags.length === 0}
          />
        ) : null}
      </header>

      <section>
        <div className="flex items-center justify-between gap-2 px-1 pb-2">
          <h2 className="text-sm font-bold text-slate-300">
            {gridMode === 'trending' ? 'Trending now' : 'All media'}
          </h2>
          <div className="flex items-center gap-1">
            {(['all', 'trending'] as const).map((item) => (
              <button
                key={item}
                type="button"
                onClick={() => setGridMode(item)}
                className={`rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize transition ${
                  gridMode === item
                    ? 'bg-brand-500/25 text-brand-200 ring-1 ring-brand-400/50'
                    : 'text-slate-400 hover:text-slate-200'
                }`}
              >
                {item === 'all' ? 'All' : 'Trending'}
              </button>
            ))}
          </div>
        </div>

        {gridMode === 'all' ? (
          <>
            {isPending ? (
              <div className="grid place-items-center py-12">
                <Spinner className="h-6 w-6" />
              </div>
            ) : null}

            {!isPending && posts.length === 0 ? (
              <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
                <p className="text-base font-semibold text-slate-200">Nothing to explore yet</p>
                <p className="mt-1 text-sm text-slate-400">
                  Photos and reels from across amteCHAT will appear here.
                </p>
              </div>
            ) : null}

            {posts.length > 0 ? (
              <div className="grid grid-cols-3 gap-1.5">
                {posts.map((post) => (
                  <button
                    key={post.id}
                    type="button"
                    onClick={() => setSelected(post)}
                    className="group relative aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10"
                    aria-label="Open media"
                  >
                    <ExploreTile post={post} />
                    <span className="absolute inset-x-0 bottom-0 hidden bg-gradient-to-t from-black/70 to-transparent p-1.5 text-left group-hover:block">
                      <p className="truncate text-[10px] text-[#fff]">{post.body || 'amteCHAT'}</p>
                    </span>
                  </button>
                ))}
              </div>
            ) : null}

            <div ref={loadMoreRef} aria-hidden="true" />
          </>
        ) : (
          <>
            {trending.isPending ? (
              <div className="grid place-items-center py-12">
                <Spinner className="h-6 w-6" />
              </div>
            ) : null}

            {!trending.isPending && trendingPosts.length === 0 ? (
              <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
                <p className="text-base font-semibold text-slate-200">No trending posts yet</p>
                <p className="mt-1 text-sm text-slate-400">
                  Highly engaged posts from the last week will rank here.
                </p>
              </div>
            ) : null}

            {trendingPosts.length > 0 ? (
              <div className="grid grid-cols-3 gap-1.5">
                {trendingPosts.map((post) => (
                  <button
                    key={post.id}
                    type="button"
                    onClick={() => setSelected(post)}
                    className="group relative aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10"
                    aria-label="Open media"
                  >
                    <ExploreTile post={post} />
                    <span className="absolute inset-x-0 bottom-0 hidden bg-gradient-to-t from-black/70 to-transparent p-1.5 text-left group-hover:block">
                      <p className="truncate text-[10px] text-[#fff]">{post.body || 'amteCHAT'}</p>
                    </span>
                  </button>
                ))}
              </div>
            ) : null}
          </>
        )}
      </section>

      <AnimatePresence>
        {selected ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/85 p-4 backdrop-blur-sm"
          >
            <div className="w-full max-w-md">
              <PostCard
                post={selected}
                cacheKey={gridMode === 'trending' ? ['posts', 'trending'] : ['posts', 'explore']}
              />
            </div>
            <button
              type="button"
              onClick={() => setSelected(null)}
              className="fixed top-4 right-4 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/10 text-lg text-[#fff] backdrop-blur transition hover:bg-white/20"
              aria-label="Close"
            >
              ✕
            </button>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  )
}

function ExploreTile({ post }: { post: Post }) {
  const image = post.media.find((item) => item.type === 'image')
  const video = post.media.find((item) => item.type === 'video')
  if (image) {
    return <img src={image.url} alt="" loading="lazy" className="h-full w-full object-cover" />
  }
  if (video) {
    return (
      <>
        <video
          src={video.url}
          muted
          playsInline
          preload="metadata"
          className="h-full w-full object-cover"
        />
        <span className="absolute top-1.5 right-1.5 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-[#fff]">
          ▶
        </span>
      </>
    )
  }
  return (
    <div className="flex h-full w-full items-end bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 p-2">
      <p className="line-clamp-2 text-[11px] leading-tight text-slate-300">{post.body}</p>
    </div>
  )
}

function SearchResults({
  tab,
  people,
  hashtags,
  empty,
}: {
  tab: Tab
  people: User[]
  hashtags: HashtagSummary[]
  empty: boolean
}) {
  const navigate = useNavigate()
  if (empty) {
    return <p className="rounded-xl bg-white/5 px-3 py-4 text-center text-xs text-slate-500">No results found.</p>
  }
  const list = tab === 'people' ? people : hashtags
  if (list.length === 0) {
    return <p className="rounded-xl bg-white/5 px-3 py-4 text-center text-xs text-slate-500">No matches yet — keep typing…</p>
  }
  return (
    <ul className="max-h-64 space-y-1 overflow-y-auto rounded-xl bg-white/5 p-1.5">
      {tab === 'people'
        ? people.map((user) => (
            <li key={user.id}>
              <button
                type="button"
                onClick={() => navigate(userProfile(user.username))}
                className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-white/10"
              >
                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[10px] font-bold text-[#fff]">
                  {(user.display_name || user.username).charAt(0).toUpperCase()}
                </span>
                <span className="min-w-0">
                  <span className="block truncate text-xs font-semibold text-slate-200">
                    {user.display_name || user.username}
                  </span>
                  <span className="block truncate text-[11px] text-slate-500">
                    @{user.username}
                  </span>
                </span>
              </button>
            </li>
          ))
        : hashtags.map((hashtag) => (
            <li key={hashtag.name}>
              <button
                type="button"
                onClick={() => navigate(hashtagPage(hashtag.name))}
                className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-white/10"
              >
                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-amber-500/20 text-xs font-bold text-amber-300">
                  #
                </span>
                <span className="min-w-0">
                  <span className="block truncate text-xs font-semibold text-slate-200">
                    #{hashtag.name}
                  </span>
                  <span className="block truncate text-[11px] text-slate-500">
                    {hashtag.posts_count.toLocaleString()} post{hashtag.posts_count === 1 ? '' : 's'}
                  </span>
                </span>
              </button>
            </li>
          ))}
    </ul>
  )
}