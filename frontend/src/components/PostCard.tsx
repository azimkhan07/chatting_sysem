import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { HeartIcon, MessageIcon, ShareIcon } from '@/components/icons'
import RichText from '@/components/RichText'
import { commentsApi, postsApi } from '@/lib/api'
import { userProfile } from '@/lib/paths'
import { timeAgo } from '@/lib/time'
import { useAuthStore } from '@/stores/authStore'
import type { Comment, Post, PostMedia } from '@/types/post'

interface PostCardProps {
  post: Post
  cacheKey?: (string | number)[]
  compact?: boolean
}

export default function PostCard({ post, cacheKey = ['posts', 'feed'], compact = false }: PostCardProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const sessionUser = useAuthStore((state) => state.user)
  const [commentsOpen, setCommentsOpen] = useState(false)
  const [commentBody, setCommentBody] = useState('')
  const [copied, setCopied] = useState(false)

  const media = post.media

  const patchPost = (postId: number, mutator: (current: Post) => Post) => {
    queryClient.setQueryData<{ pages: { posts: Post[] }[] }>(cacheKey, (current) => {
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
    mutationFn: () => (post.liked_by_me ? postsApi.unlike(post.id) : postsApi.like(post.id)),
    onMutate: () => {
      const previous = queryClient.getQueryData(cacheKey)
      patchPost(post.id, (p) => ({
        ...p,
        liked_by_me: !p.liked_by_me,
        likes_count: p.likes_count + (p.liked_by_me ? -1 : 1),
      }))
      return previous
    },
    onError: (_error, _vars, rollback) => {
      if (rollback !== undefined) queryClient.setQueryData(cacheKey, rollback)
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
          {post.author.display_name.charAt(0).toUpperCase()}
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
              <RichText text={post.body} />
            </p>
          ) : null}
          {media.length > 0 ? <PostMediaGrid media={media} compact={compact} /> : null}

          <div className="mt-3 flex items-center gap-1 border-t border-white/5 pt-2.5">
            <ActionButton
              active={post.liked_by_me}
              activeClass="text-rose-400"
              onClick={() => toggleLike.mutate()}
              label={post.liked_by_me ? 'Unlike post' : 'Like post'}
              icon={<HeartIcon className={`h-[18px] w-[18px] ${post.liked_by_me ? 'fill-rose-500 text-rose-500' : ''}`} />}
              count={post.likes_count}
            />
            <ActionButton
              active={commentsOpen}
              activeClass="text-brand-300"
              onClick={() => setCommentsOpen((open) => !open)}
              label="Comments"
              icon={<MessageIcon className="h-[18px] w-[18px]" />}
              count={post.comments_count}
            />
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
            <CommentsPanel
              comments={comments}
              sessionUser={sessionUser}
              commentBody={commentBody}
              onBodyChange={setCommentBody}
              onPostComment={handleCommentSubmit}
              posting={postComment.isPending}
              loading={commentsQuery.isPending}
            />
          ) : null}
        </div>
      </div>
    </motion.article>
  )
}

function ActionButton({
  active,
  activeClass,
  onClick,
  label,
  icon,
  count,
}: {
  active: boolean
  activeClass: string
  onClick: () => void
  label: string
  icon: React.ReactNode
  count: number
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition ${
        active ? activeClass : 'text-slate-400 hover:text-slate-200'
      }`}
      aria-label={label}
      aria-expanded={active}
    >
      {icon}
      {count > 0 ? count.toLocaleString() : null}
    </button>
  )
}

export function CommentsPanel({
  comments,
  sessionUser,
  commentBody,
  onBodyChange,
  onPostComment,
  posting,
  loading,
}: {
  comments: Comment[]
  sessionUser: { display_name: string } | null
  commentBody: string
  onBodyChange: (value: string) => void
  onPostComment: (event: React.FormEvent) => void
  posting: boolean
  loading: boolean
}) {
  return (
    <div className="mt-3 space-y-3 border-t border-white/5 pt-3">
      {loading ? (
        <p className="text-xs text-slate-500">Loading comments…</p>
      ) : (
        <ul className="space-y-2.5">
          {comments.map((comment) => (
            <li key={comment.id} className="flex gap-2">
              <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500/20 text-[10px] font-bold text-brand-200">
                {comment.author.display_name.charAt(0).toUpperCase()}
              </span>
              <div className="min-w-0">
                <p className="text-xs leading-relaxed text-slate-300">
                  <span className="font-semibold text-slate-200">
                    {comment.author.display_name}
                  </span>{' '}
                  <RichText text={comment.body} />
                </p>
                <p className="mt-0.5 text-[10px] text-slate-500">
                  @{comment.author.username} · {timeAgo(comment.created_at)}
                </p>
              </div>
            </li>
          ))}
          {comments.length === 0 ? (
            <li className="text-xs text-slate-500">
              No comments yet — start the conversation.
            </li>
          ) : null}
        </ul>
      )}

      <form onSubmit={onPostComment} className="flex items-center gap-2">
        <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[10px] font-bold text-[#fff]">
          {(sessionUser?.display_name ?? '?').charAt(0).toUpperCase()}
        </span>
        <input
          value={commentBody}
          onChange={(event) => onBodyChange(event.target.value)}
          placeholder="Add a comment…"
          maxLength={2000}
          className="input-field flex-1 !py-2 text-xs"
          aria-label="Comment body"
        />
        <button
          type="submit"
          disabled={!commentBody.trim() || posting}
          className="btn-primary w-auto !px-3 !py-2 text-xs disabled:opacity-50"
        >
          Post
        </button>
      </form>
    </div>
  )
}

export function PostMediaGrid({ media, compact = false }: { media: PostMedia[]; compact?: boolean }) {
  const images = media.filter((item) => item.type === 'image')
  const videos = media.filter((item) => item.type === 'video')
  const cols = compact ? 1 : images.length >= 3 ? 3 : Math.max(images.length, 1)

  return (
    <div className="mt-3 space-y-2">
      {images.length > 0 ? (
        <div
          className={`grid gap-2 ${cols === 1 ? 'grid-cols-1' : cols === 2 ? 'grid-cols-2' : 'grid-cols-3'}`}
        >
          {images.map((image, index) => (
            <div
              key={image.id}
              className={`overflow-hidden rounded-2xl bg-white/5 ring-1 ring-white/10 ${
                cols === 1 ? 'aspect-video max-h-96' : 'aspect-square'
              }`}
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