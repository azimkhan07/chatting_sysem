import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import StoriesRow from '@/components/StoriesRow'
import { HeartIcon, MessageIcon, ShareIcon } from '@/components/icons'
import { commentsApi, postsApi } from '@/lib/api'
import { userProfile } from '@/lib/paths'
import { timeAgo } from '@/lib/time'
import { useAuthStore } from '@/stores/authStore'
import type { Comment, Post, PostMedia } from '@/types/post'

const MAX_POST_LENGTH = 5000
const ACCEPTED_EXTENSIONS = /\.(jpe?g|png|webp|gif|mp4|webm|mov)$/i

interface PendingMedia {
  file: File
  previewUrl: string
}

export default function Home() {
  const sessionUser = useAuthStore((state) => state.user)
  const queryClient = useQueryClient()
  const [body, setBody] = useState('')
  const [media, setMedia] = useState<PendingMedia[]>([])
  const fileInputRef = useRef<HTMLInputElement>(null)
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

  useEffect(() => {
    const urls = media.map((item) => item.previewUrl)
    return () => urls.forEach((url) => URL.revokeObjectURL(url))
  }, [media])

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
    mutationFn: (form: { body: string; media: File[] }) => postsApi.create(form),
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
      setMedia([])
    },
  })

  const posts = feedPages?.pages.flatMap((page) => page.posts) ?? []

  function addFiles(list: FileList | null) {
    if (!list) return
    const files = Array.from(list).filter(
      (file) => file.size > 0 && ACCEPTED_EXTENSIONS.test(file.name),
    )
    if (files.length === 0) return

    setMedia((current) => [
      ...current,
      ...files.map((file) => ({ file, previewUrl: URL.createObjectURL(file) })),
    ].slice(0, 5))
  }

  function removeMedia(index: number) {
    setMedia((current) => {
      const next = current.filter((_, i) => i !== index)
      return next
    })
  }

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    const text = body.trim()
    if (createPost.isPending) return
    if (!text && media.length === 0) return
    if (text.length > MAX_POST_LENGTH) return
    createPost.mutate({ body: text, media: media.map((item) => item.file) })
  }

  const canPost = body.trim().length > 0 || media.length > 0

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

      <StoriesRow />

      <motion.form
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: 'easeOut' }}
        onSubmit={handleSubmit}
        className="glass-card p-4"
      >
        <div className="flex gap-3">
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-[#fff]">
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

        {media.length > 0 ? (
          <div className="mt-3 grid grid-cols-3 gap-2">
            {media.map((item, index) => (
              <div
                key={item.previewUrl}
                className="group relative aspect-square overflow-hidden rounded-2xl bg-white/5"
              >
                {item.file.type.startsWith('video/') ? (
                  <video
                    src={item.previewUrl}
                    muted
                    playsInline
                    preload="metadata"
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <img
                    src={item.previewUrl}
                    alt="Attached preview"
                    className="h-full w-full object-cover"
                  />
                )}
                <button
                  type="button"
                  onClick={() => removeMedia(index)}
                  aria-label="Remove attachment"
                  className="absolute top-1 right-1 grid h-5 w-5 place-items-center rounded-full bg-black/70 text-xs text-[#fff] backdrop-blur transition hover:bg-rose-500"
                >
                  ×
                </button>
              </div>
            ))}
          </div>
        ) : null}

        <div className="mt-2 flex items-center justify-between border-t border-white/5 pt-3">
          <div className="flex items-center gap-2">
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
              multiple
              className="sr-only"
              onChange={(event) => addFiles(event.target.files)}
              aria-label="Attach media"
            />
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              className="btn-quiet px-3 py-1.5 text-xs"
              disabled={media.length >= 5}
            >
              {media.length >= 5 ? 'Limit reached' : 'Add photo / video'}
            </button>
            <p
              className={`text-xs ${
                body.length > MAX_POST_LENGTH ? 'text-rose-400' : 'text-slate-500'
              }`}
              aria-live="polite"
            >
              {body.length.toLocaleString()} / {MAX_POST_LENGTH.toLocaleString()}
            </p>
          </div>
          <button
            type="submit"
            disabled={!canPost || body.length > MAX_POST_LENGTH || createPost.isPending}
            className="btn-primary w-auto px-4 py-2"
          >
            {createPost.isPending ? <Spinner className="h-4 w-4" /> : null}
            {createPost.isPending ? 'Posting…' : 'Post'}
          </button>
        </div>

        {createPost.isError ? (
          <p className="mt-2 text-xs text-rose-400">{createPost.error.message}</p>
        ) : null}
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
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const sessionUser = useAuthStore((state) => state.user)
  const initials = post.author.display_name.charAt(0).toUpperCase()
  const [commentsOpen, setCommentsOpen] = useState(false)
  const [commentBody, setCommentBody] = useState('')
  const [copied, setCopied] = useState(false)

  const feedKey = ['posts', 'feed']

  const patchPost = (postId: number, mutator: (post: Post) => Post) => {
    queryClient.setQueryData<{ pages: { posts: Post[] }[] }>(feedKey, (current) => {
      if (!current) return current
      return {
        ...current,
        pages: current.pages.map((page) => ({
          ...page,
          posts: page.posts.map((p) => (p.id === postId ? mutator(p) : p)),
        })),
      }
    })
  }

  const toggleLike = useMutation({
    mutationFn: () =>
      post.liked_by_me ? postsApi.unlike(post.id) : postsApi.like(post.id),
    onMutate: () => {
      const previous = queryClient.getQueryData(feedKey)
      patchPost(post.id, (p) => ({
        ...p,
        liked_by_me: !p.liked_by_me,
        likes_count: p.likes_count + (p.liked_by_me ? -1 : 1),
      }))
      return previous
    },
    onError: (_error, _vars, rollback) => {
      if (rollback !== undefined) queryClient.setQueryData(feedKey, rollback)
    },
  })

  const commentsQuery = useQuery({
    queryKey: ['comments', post.id],
    queryFn: () => commentsApi.list(post.id),
    enabled: commentsOpen,
    placeholderData: keepPreviousData,
  })
  const comments = commentsQuery.data?.comments ?? []

  const postComment = useMutation({
    mutationFn: (body: string) => commentsApi.create(post.id, body),
    onSuccess: (result) => {
      setCommentBody('')
      queryClient.setQueryData<{ comments: Comment[] }>(['comments', post.id], (current) => ({
        comments: [result.comment, ...(current?.comments ?? [])],
      }))
      patchPost(post.id, (p) => ({ ...p, comments_count: p.comments_count + 1 }))
    },
  })

  async function handleShare() {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: 'amteCHAT post', text: post.body, url })
        return
      }
    } catch {
      return
    }
    await navigator.clipboard.writeText(url)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1500)
  }

  function handleCommentSubmit(event: React.FormEvent) {
    event.preventDefault()
    const body = commentBody.trim()
    if (!body || postComment.isPending) return
    postComment.mutate(body)
  }

  return (
    <motion.article
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, ease: 'easeOut' }}
      className="glass-card p-4"
    >
      <div className="flex gap-3">
        <button
          type="button"
          onClick={() => navigate(userProfile(post.author.username))}
          className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500/30 to-fuchsia-500/30 text-sm font-bold text-brand-200 transition hover:from-brand-500/50 hover:to-fuchsia-500/50"
          aria-label={`View ${post.author.display_name}'s profile`}
        >
          {initials}
        </button>
        <div className="min-w-0 flex-1">
          <button
            type="button"
            onClick={() => navigate(userProfile(post.author.username))}
            className="flex flex-wrap items-baseline gap-x-2 text-left transition hover:opacity-80"
          >
            <span className="text-sm font-semibold text-slate-200">
              {post.author.display_name}
            </span>
            {post.author.is_verified ? (
              <span className="badge bg-sky-500/15 text-sky-300">✓</span>
            ) : null}
            <span className="text-xs text-slate-500">
              @{post.author.username} · {timeAgo(post.created_at)}
            </span>
          </button>
          {post.body ? (
            <p className="mt-1.5 text-sm leading-relaxed whitespace-pre-wrap text-slate-200">
              {post.body}
            </p>
          ) : null}
          {post.media.length > 0 ? <PostMediaGrid media={post.media} /> : null}

          <div className="mt-3 flex items-center gap-1 border-t border-white/5 pt-2.5">
            <button
              type="button"
              onClick={() => toggleLike.mutate()}
              className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition ${
                post.liked_by_me
                  ? 'text-rose-400'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
              aria-label={post.liked_by_me ? 'Unlike post' : 'Like post'}
            >
              <HeartIcon
                className={`h-[18px] w-[18px] ${post.liked_by_me ? 'fill-rose-500 text-rose-500' : ''}`}
              />
              {post.likes_count > 0 ? post.likes_count.toLocaleString() : null}
            </button>

            <button
              type="button"
              onClick={() => setCommentsOpen((open) => !open)}
              className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition ${
                commentsOpen ? 'text-brand-300' : 'text-slate-400 hover:text-slate-200'
              }`}
              aria-expanded={commentsOpen}
            >
              <MessageIcon className="h-[18px] w-[18px]" />
              {post.comments_count > 0 ? post.comments_count.toLocaleString() : null}
            </button>

            <button
              type="button"
              onClick={() => void handleShare()}
              className="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold text-slate-400 transition hover:text-slate-200"
              aria-label="Share post"
            >
              <ShareIcon className="h-[18px] w-[18px]" />
              {copied ? 'Copied!' : null}
            </button>
          </div>

          {commentsOpen ? (
            <div className="mt-3 space-y-3 border-t border-white/5 pt-3">
              <ul className="space-y-2.5">
                {comments.map((comment) => (
                  <CommentRow key={comment.id} comment={comment} />
                ))}
                {comments.length === 0 ? (
                  <li className="text-xs text-slate-500">
                    No comments yet — start the conversation.
                  </li>
                ) : null}
              </ul>

              <form onSubmit={handleCommentSubmit} className="flex items-center gap-2">
                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[10px] font-bold text-[#fff]">
                  {(sessionUser?.display_name ?? '?').charAt(0).toUpperCase()}
                </span>
                <input
                  value={commentBody}
                  onChange={(event) => setCommentBody(event.target.value)}
                  placeholder="Add a comment…"
                  maxLength={2000}
                  className="input-field flex-1 !py-2 text-xs"
                  aria-label="Comment body"
                />
                <button
                  type="submit"
                  disabled={!commentBody.trim() || postComment.isPending}
                  className="btn-primary w-auto !px-3 !py-2 text-xs disabled:opacity-50"
                >
                  Post
                </button>
              </form>
            </div>
          ) : null}
        </div>
      </div>
    </motion.article>
  )
}

function CommentRow({ comment }: { comment: Comment }) {
  const initials = comment.author.display_name.charAt(0).toUpperCase()

  return (
    <li className="flex gap-2">
      <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500/20 text-[10px] font-bold text-brand-200">
        {initials}
      </span>
      <div className="min-w-0">
        <p className="text-xs leading-relaxed text-slate-300">
          <span className="font-semibold text-slate-200">
            {comment.author.display_name}
          </span>{' '}
          {comment.body}
        </p>
        <p className="mt-0.5 text-[10px] text-slate-500">
          @{comment.author.username} · {timeAgo(comment.created_at)}
        </p>
      </div>
    </li>
  )
}

function PostMediaGrid({ media }: { media: PostMedia[] }) {
  const images = media.filter((item) => item.type === 'image')
  const videos = media.filter((item) => item.type === 'video')
  const cols = images.length >= 3 ? 3 : Math.max(images.length, 1)

  return (
    <div className="mt-3 space-y-2">
      {images.length > 0 ? (
        <div
          className={`grid gap-2 ${cols === 1 ? 'grid-cols-1' : cols === 2 ? 'grid-cols-2' : 'grid-cols-3'}`}
        >
          {images.map((image, index) => (
            <div
              key={image.id}
              className={`overflow-hidden rounded-2xl bg-white/5 ring-1 ring-white/10 ${cols === 1 ? 'aspect-video max-h-96' : 'aspect-square'}`}
            >
              <img
                src={image.url}
                alt={`Media ${index + 1}`}
                loading="lazy"
                className="h-full w-full object-cover"
              />
            </div>
          ))}
        </div>
      ) : null}

      {videos.map((video) => (
        <video
          key={video.id}
          src={video.url}
          controls
          preload="metadata"
          playsInline
          className="aspect-video w-full rounded-2xl bg-black/40 ring-1 ring-white/10"
        />
      ))}
    </div>
  )
}