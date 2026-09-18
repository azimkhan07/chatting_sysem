import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { CommentsPanel } from '@/components/PostCard'
import RichText from '@/components/RichText'
import { HeartIcon, MessageIcon, ShareIcon } from '@/components/icons'
import { commentsApi, postsApi } from '@/lib/api'
import { userProfile } from '@/lib/paths'
import { timeAgo } from '@/lib/time'
import { useAuthStore } from '@/stores/authStore'
import type { Post } from '@/types/post'

const ACTION_BASE =
  'grid h-10 w-10 place-items-center rounded-full bg-black/40 text-[#fff] backdrop-blur transition active:scale-90'

export default function ReelCard({ post }: { post: Post }) {
  const navigate = useNavigate()
  const sessionUser = useAuthStore((state) => state.user)
  const videoRef = useRef<HTMLVideoElement>(null)
  const cardRef = useRef<HTMLElement>(null)
  const [muted, setMuted] = useState(true)
  const [playing, setPlaying] = useState(false)
  const [commentsOpen, setCommentsOpen] = useState(false)
  const [commentBody, setCommentBody] = useState('')
  const [copied, setCopied] = useState(false)
  const [liked, setLiked] = useState(post.liked_by_me)
  const [likesCount, setLikesCount] = useState(post.likes_count)

  const video = post.media.find((item) => item.type === 'video')

  useEffect(() => {
    const node = cardRef.current
    if (!node || !video) return

    const observer = new IntersectionObserver(
      ([entry]) => {
        const visible = entry.isIntersecting && entry.intersectionRatio > 0.6
        setPlaying(visible)
      },
      { threshold: [0.6] },
    )
    observer.observe(node)

    return () => observer.disconnect()
  }, [video])

  useEffect(() => {
    const el = videoRef.current
    if (!el) return
    el.muted = muted
    if (playing) {
      void el.play().catch(() => setPlaying(false))
    } else {
      el.pause()
    }
  }, [playing, muted, post.id])

  const toggleLike = useMutation({
    mutationFn: () => (liked ? postsApi.unlike(post.id) : postsApi.like(post.id)),
    onMutate: () => {
      setLiked((value) => !value)
      setLikesCount((count) => count + (liked ? -1 : 1))
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
    onSuccess: (_result) => {
      setCommentBody('')
    },
  })

  async function handleShare() {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: 'amteCHAT reel', text: post.body, url })
        return
      }
    } catch {
      return
    }
    await navigator.clipboard.writeText(url)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1500)
  }

  return (
    <motion.article
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, ease: 'easeOut' }}
      className="mx-auto w-full max-w-md"
    >
      <div className="relative overflow-hidden rounded-3xl bg-black ring-1 ring-white/10">
        {video ? (
          <video
            ref={videoRef}
            src={video.url}
            autoPlay
            muted
            loop
            playsInline
            preload="metadata"
            className="aspect-[9/16] w-full object-cover"
            role="button"
            aria-label="Reel video"
            onClick={() => setMuted((value) => !value)}
          />
        ) : (
          <div className="grid aspect-[9/16] w-full place-items-center bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 text-sm text-slate-400">
            Unavailable reel
          </div>
        )}

        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-black/40" />

        <div className="absolute top-3 left-3 right-3 flex items-center gap-2">
          <button
            type="button"
            onClick={() => navigate(userProfile(post.author.username))}
            className="flex items-center gap-2 rounded-full bg-black/40 py-1 pr-3 pl-1 text-[#fff] backdrop-blur transition hover:bg-black/60"
          >
            <span className="grid h-7 w-7 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[10px] font-bold text-[#fff]">
              {post.author.display_name.charAt(0).toUpperCase()}
            </span>
            <span className="max-w-36 truncate text-xs font-semibold">
              {post.author.display_name}
            </span>
          </button>
          <span className="ml-1 text-[10px] text-[rgba(255,255,255,0.7)]">
            {timeAgo(post.created_at)}
          </span>
        </div>

        <div className="absolute right-2 bottom-24 z-10 flex flex-col items-center gap-3">
          <button
            type="button"
            onClick={() => toggleLike.mutate()}
            className={ACTION_BASE}
            aria-label={liked ? 'Unlike reel' : 'Like reel'}
          >
            <HeartIcon
              className={`h-5 w-5 ${liked ? 'fill-rose-500 text-rose-500' : ''}`}
            />
          </button>
          <span className="text-[10px] font-semibold text-[#fff]">
            {likesCount > 0 ? likesCount.toLocaleString() : ''}
          </span>

          <button
            type="button"
            onClick={() => setCommentsOpen((open) => !open)}
            className={ACTION_BASE}
            aria-label="Comments"
          >
            <MessageIcon className="h-5 w-5" />
          </button>
          <span className="text-[10px] font-semibold text-[#fff]">
            {post.comments_count > 0 ? post.comments_count.toLocaleString() : ''}
          </span>

          <button
            type="button"
            onClick={() => void handleShare()}
            className={ACTION_BASE}
            aria-label="Share reel"
          >
            <ShareIcon className="h-5 w-5" />
          </button>
          {copied ? <span className="text-[9px] text-[#fff]">Copied!</span> : null}
        </div>

        <div className="absolute inset-x-0 bottom-0 z-10 p-4">
          {post.body ? (
            <p className="text-sm leading-snug text-[#fff] [text-shadow:0_1px_2px_rgba(0,0,0,0.8)]">
              <RichText
                text={post.body}
                mentionClass="font-semibold text-sky-300 hover:underline"
                hashtagClass="font-semibold text-amber-300 hover:underline"
              />
            </p>
          ) : null}

          <button
            type="button"
            onClick={() => setMuted((value) => !value)}
            className="mt-2 inline-flex items-center gap-1 rounded-full bg-black/40 px-2 py-1 text-[10px] text-[rgba(255,255,255,0.85)] backdrop-blur transition hover:bg-black/60"
            aria-label={muted ? 'Unmute' : 'Mute'}
          >
            {muted ? '🔇 Sound off' : '🔊 Sound on'}
          </button>

          <AnimatePresence>
            {commentsOpen ? (
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: 20 }}
                transition={{ duration: 0.18, ease: 'easeOut' }}
                className="mt-3 rounded-2xl bg-black/60 p-3 backdrop-blur"
              >
                <CommentsPanel
                  comments={comments}
                  sessionUser={sessionUser}
                  commentBody={commentBody}
                  onBodyChange={setCommentBody}
                  onPostComment={(event) => {
                    event.preventDefault()
                    const body = commentBody.trim()
                    if (!body || postComment.isPending) return
                    postComment.mutate(body)
                  }}
                  posting={postComment.isPending}
                  loading={commentsQuery.isPending}
                />
              </motion.div>
            ) : null}
          </AnimatePresence>
        </div>
      </div>
    </motion.article>
  )
}