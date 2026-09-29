import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { HeartIcon, MessageIcon, ShareIcon } from '@/components/icons'
import ReportDialog from '@/components/moderation/ReportDialog'
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
  const [replyTo, setReplyTo] = useState<Comment | null>(null)
  const [copied, setCopied] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  const [reporting, setReporting] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)

  const media = post.media

  const patchPost = (postId: number, mutator: (current: Post) => Post) => {
    queryClient.setQueryData(
      cacheKey,
      (current: { pages?: { posts: Post[] }[]; posts?: Post[] } | undefined) => {
        if (!current) return undefined
        if (Array.isArray(current.pages)) {
          return {
            ...current,
            pages: current.pages.map((page) => ({
              ...page,
              posts: page.posts.map((p) => (p.id === postId ? mutator(p) : p)),
            })),
          }
        }
        if (Array.isArray(current.posts)) {
          return { ...current, posts: current.posts.map((p) => (p.id === postId ? mutator(p) : p)) }
        }
        return current
      },
    )
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
    // The optimistic bump is only a guess: settle on the server counters so a
    // failed or replayed request can never leave the badge drifting.
    onSuccess: (result) => {
      patchPost(post.id, (p) => ({
        ...p,
        liked_by_me: result.liked,
        likes_count: result.likes_count,
      }))
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
    mutationFn: ({ body, parentId }: { body: string; parentId?: number }) =>
      commentsApi.create(post.id, body, parentId),
    onSuccess: (result, variables) => {
      setCommentBody('')
      setReplyTo(null)
      queryClient.setQueryData<{ comments: Comment[] }>(['comments', post.id], (current) => {
        const comments = current?.comments ?? []

        if (variables.parentId === undefined) {
          return { comments: [result.comment, ...comments] }
        }

        // A reply belongs under its root comment, not at the top level.
        return {
          comments: comments.map((comment) =>
            comment.id === variables.parentId
              ? {
                  ...comment,
                  reply_count: comment.reply_count + 1,
                  replies:
                    comment.replies.length >= 3
                      ? comment.replies
                      : [...comment.replies, result.comment],
                }
              : comment,
          ),
        }
      })
      patchPost(post.id, (p) => ({ ...p, comments_count: p.comments_count + 1 }))
    },
  })

  const share = useMutation({
    mutationFn: () => postsApi.share(post.id),
    onMutate: () => {
      const previous = queryClient.getQueryData(cacheKey)
      patchPost(post.id, (p) => ({ ...p, shares_count: p.shares_count + 1 }))
      return previous
    },
    onError: (_error, _vars, rollback) => {
      if (rollback !== undefined) queryClient.setQueryData(cacheKey, rollback)
    },
    onSuccess: (result) => {
      patchPost(post.id, (p) => ({ ...p, shares_count: result.shares_count }))
    },
  })

  async function handleShare() {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: 'amteCHAT post', text: post.body, url })
        share.mutate()
        return
      }
    } catch {
      return
    }
    await navigator.clipboard.writeText(url)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1500)
    share.mutate()
  }

  /**
   * Removal is optimistic, because the post is on screen and the reader asked
   * for it to be gone. Every cached copy of this feed is patched, not just the
   * one the card happens to be rendered in - the same post is in the home feed,
   * the profile grid and explore at once, and leaving it in two of them is how
   * a deleted post comes back on a scroll.
   */
  const remove = useMutation({
    mutationFn: () => postsApi.remove(post.id),
    onMutate: async () => {
      await queryClient.cancelQueries({ queryKey: cacheKey })
      const previous = queryClient.getQueryData(cacheKey)
      queryClient.setQueryData(
        cacheKey,
        (current: { pages?: { posts: Post[] }[]; posts?: Post[] } | undefined) => {
          if (!current) return undefined
          if (Array.isArray(current.pages)) {
            return {
              ...current,
              pages: current.pages.map((page) => ({
                ...page,
                posts: page.posts.filter((p) => p.id !== post.id),
              })),
            }
          }
          if (Array.isArray(current.posts)) {
            return { ...current, posts: current.posts.filter((p) => p.id !== post.id) }
          }
          return current
        },
      )
      return previous
    },
    onError: (_error, _vars, rollback) => {
      if (rollback !== undefined) queryClient.setQueryData(cacheKey, rollback)
    },
    onSuccess: () => {
      // The optimistic update above only knows about the one list this card was
      // rendered in. Every post list shares the ['posts', ...] prefix - feed,
      // reels, explore, trending, profile, user profile - so invalidating the
      // prefix refetches the others, which is what stops a deleted post from
      // surviving in a feed the reader has not navigated back to yet.
      queryClient.invalidateQueries({ queryKey: ['posts'] })
    },
  })

  function handleCommentSubmit(event: React.FormEvent) {
    event.preventDefault()
    const body = commentBody.trim()
    if (!body || postComment.isPending) return
    postComment.mutate(
      replyTo === null ? { body } : { body, parentId: replyTo.id },
    )
  }

  const isOwnPost = sessionUser?.id === post.author.id

  // Dismissed on an outside click rather than left open: a menu that has to be
  // dismissed with a second click on its trigger covers the caption underneath.
  useEffect(() => {
    if (!menuOpen) return
    function onPointerDown(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) setMenuOpen(false)
    }
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') setMenuOpen(false)
    }
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [menuOpen])

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

          {post.tagged_users?.length ? (
            <p className="mt-1 text-xs text-slate-400">
              with{' '}
              {post.tagged_users.map((user, index) => (
                <span key={user.id}>
                  {index > 0 ? ', ' : ''}
                  <button
                    type="button"
                    onClick={() => navigate(userProfile(user.username))}
                    className="font-medium text-brand-300 transition hover:text-brand-200 hover:underline"
                  >
                    @{user.username}
                  </button>
                </span>
              ))}
            </p>
          ) : null}

          {post.location ? (
            <p className="mt-1 flex items-center gap-1 text-xs text-slate-400">
              <span aria-hidden="true">⌖</span>
              <span className="truncate">{post.location}</span>
            </p>
          ) : null}

          {post.song ? (
            <p className="mt-1 flex items-center gap-1.5 text-xs text-slate-400">
              <span aria-hidden="true">♫</span>
              <span className="truncate">
                {post.song.name}
                {post.song.artist ? ` — ${post.song.artist}` : ''}
              </span>
            </p>
          ) : null}

          {media.length > 0 ? <PostMediaGrid media={media} compact={compact} /> : null}

          <div className="flex items-center justify-between gap-2 border-t border-white/5 pt-2.5">
            <div className="flex items-center gap-1">
            <ActionButton
              active={post.liked_by_me}
              activeClass="text-rose-400"
              onClick={() => {
                if (!toggleLike.isPending) toggleLike.mutate()
              }}
              disabled={toggleLike.isPending}
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
            <ActionButton
              active={copied}
              activeClass="text-brand-300"
              onClick={() => {
                if (!share.isPending) void handleShare()
              }}
              disabled={share.isPending}
              label="Share post"
              icon={<ShareIcon className="h-[18px] w-[18px]" />}
              count={copied ? 0 : post.shares_count}
            />
            {copied ? (
              <span className="text-xs font-semibold text-brand-300">Link copied</span>
            ) : null}
            </div>

            <div className="relative" ref={menuRef}>
              <button
                type="button"
                onClick={() => setMenuOpen((open) => !open)}
                aria-label="Post options"
                aria-expanded={menuOpen}
                className="rounded-full p-1.5 text-slate-500 transition hover:bg-white/5 hover:text-slate-200"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <circle cx="12" cy="5" r="2" />
                  <circle cx="12" cy="12" r="2" />
                  <circle cx="12" cy="19" r="2" />
                </svg>
              </button>

              {menuOpen ? (
                <div className="absolute right-0 bottom-full z-20 mb-1 w-44 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 py-1 shadow-xl">
                  {isOwnPost ? (
                    <button
                      type="button"
                      disabled={remove.isPending}
                      onClick={() => {
                        setMenuOpen(false)
                        remove.mutate()
                      }}
                      className="block w-full px-4 py-2 text-left text-sm text-rose-300 transition hover:bg-white/5 disabled:opacity-50"
                    >
                      Delete post
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => {
                        setMenuOpen(false)
                        setReporting(true)
                      }}
                      className="block w-full px-4 py-2 text-left text-sm text-slate-300 transition hover:bg-white/5"
                    >
                      Report post
                    </button>
                  )}
                </div>
              ) : null}
            </div>
          </div>

          {remove.isError ? (
            <p className="mt-2 text-xs text-rose-400">Could not delete that post. Try again.</p>
          ) : null}

          {commentsOpen ? (
            <CommentsPanel
              comments={comments}
              sessionUser={sessionUser}
              commentBody={commentBody}
              onBodyChange={setCommentBody}
              onPostComment={handleCommentSubmit}
              posting={postComment.isPending}
              loading={commentsQuery.isPending}
              replyTo={replyTo}
              onReplyTo={setReplyTo}
            />
          ) : null}
        </div>
      </div>

      {reporting ? (
        <ReportDialog
          targetType="post"
          targetId={post.id}
          subject={`post by @${post.author.username}`}
          onClose={() => setReporting(false)}
        />
      ) : null}
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
  disabled = false,
}: {
  active: boolean
  activeClass: string
  onClick: () => void
  label: string
  icon: React.ReactNode
  count: number
  disabled?: boolean
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${
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
  replyTo,
  onReplyTo,
}: {
  comments: Comment[]
  sessionUser: { display_name: string } | null
  commentBody: string
  onBodyChange: (value: string) => void
  onPostComment: (event: React.FormEvent) => void
  posting: boolean
  loading: boolean
  replyTo: Comment | null
  onReplyTo: (comment: Comment | null) => void
}) {
  return (
    <div className="mt-3 space-y-3 border-t border-white/5 pt-3">
      {loading ? (
        <p className="text-xs text-slate-500">Loading comments…</p>
      ) : (
        <ul className="space-y-2.5">
          {comments.map((comment) => (
            <li key={comment.id}>
              <CommentRow comment={comment} onReply={onReplyTo} />
              {comment.replies.length > 0 ? (
                <ul className="mt-2 space-y-2 border-l border-white/10 pl-3">
                  {comment.replies.map((reply) => (
                    <li key={reply.id}>
                      <CommentRow comment={reply} />
                    </li>
                  ))}
                </ul>
              ) : null}
              {comment.reply_count > comment.replies.length ? (
                <p className="mt-1 pl-1 text-[10px] text-slate-500">
                  {comment.reply_count - comment.replies.length} more
                  {comment.reply_count - comment.replies.length === 1 ? ' reply' : ' replies'}
                </p>
              ) : null}
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
          placeholder={
            replyTo ? `Replying to @${replyTo.author.username}` : 'Add a comment…'
          }
          maxLength={2000}
          className="input-field flex-1 !py-2 text-xs"
          aria-label="Comment body"
        />
        {replyTo ? (
          <button
            type="button"
            onClick={() => onReplyTo(null)}
            className="btn-quiet w-auto !px-2 !py-2 text-[11px]"
            aria-label="Cancel reply"
          >
            ✕
          </button>
        ) : null}
        <button
          type="submit"
          disabled={!commentBody.trim() || posting}
          className="btn-primary w-auto !px-3 !py-2 text-xs disabled:opacity-50"
        >
          {posting ? '…' : 'Post'}
        </button>
      </form>
    </div>
  )
}

function CommentRow({
  comment,
  onReply,
}: {
  comment: Comment
  onReply?: (comment: Comment) => void
}) {
  const [reporting, setReporting] = useState(false)

  return (
    <div className="flex gap-2">
      <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500/20 text-[10px] font-bold text-brand-200">
        {comment.author.display_name.charAt(0).toUpperCase()}
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-xs leading-relaxed text-slate-300">
          <span className="font-semibold text-slate-200">
            {comment.author.display_name}
          </span>{' '}
          <RichText text={comment.body} />
        </p>
        <p className="mt-0.5 flex items-center gap-2 text-[10px] text-slate-500">
          <span>
            @{comment.author.username} · {timeAgo(comment.created_at)}
          </span>
          {onReply ? (
            <button
              type="button"
              onClick={() => onReply(comment)}
              className="font-semibold text-slate-400 transition hover:text-brand-300"
            >
              Reply
            </button>
          ) : null}
          <button
            type="button"
            onClick={() => setReporting(true)}
            className="font-semibold text-slate-500 transition hover:text-rose-300"
          >
            Report
          </button>
        </p>
      </div>

      {reporting ? (
        <ReportDialog
          targetType="comment"
          targetId={comment.id}
          subject={`comment by @${comment.author.username}`}
          onClose={() => setReporting(false)}
        />
      ) : null}
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