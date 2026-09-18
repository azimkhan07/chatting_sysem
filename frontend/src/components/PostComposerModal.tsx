import { useMutation, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { postsApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { Post } from '@/types/post'

const MAX_POST_LENGTH = 5000
const ACCEPTED_EXTENSIONS = /\.(jpe?g|png|webp|gif|mp4|webm|mov)$/i

interface PendingMedia {
  file: File
  previewUrl: string
}

interface PostComposerModalProps {
  onClose: () => void
}

export default function PostComposerModal({ onClose }: PostComposerModalProps) {
  const queryClient = useQueryClient()
  const sessionUser = useAuthStore((state) => state.user)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [body, setBody] = useState('')
  const [media, setMedia] = useState<PendingMedia[]>([])

  useEffect(() => {
    const urls = media.map((item) => item.previewUrl)
    return () => urls.forEach((url) => URL.revokeObjectURL(url))
  }, [media])

  const createPost = useMutation({
    mutationFn: (form: { body: string; media: File[] }) => postsApi.create(form),
    onSuccess: (result) => {
      stampFeed(result.post, ['posts', 'reels'])
      stampFeed(result.post, ['posts', 'explore'])
      onClose()
    },
  })

  function stampFeed(post: Post, cacheKey: (string | number)[]) {
    queryClient.setQueryData<{ pages: { posts: Post[]; next_cursor: string | null }[] }>(
      cacheKey,
      (current) => {
        if (!current) return current
        return {
          ...current,
          pages: current.pages.map((page, index) =>
            index === 0 ? { ...page, posts: [post, ...page.posts] } : page,
          ),
        }
      },
    )
  }

  function addFiles(list: FileList | null) {
    if (!list) return
    const files = Array.from(list).filter(
      (file) => file.size > 0 && ACCEPTED_EXTENSIONS.test(file.name),
    )
    if (files.length === 0) return

    setMedia((current) =>
      [...current, ...files.map((file) => ({ file, previewUrl: URL.createObjectURL(file) }))].slice(
        0,
        5,
      ),
    )
  }

  function removeMedia(index: number) {
    setMedia((current) => current.filter((_, i) => i !== index))
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
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm">
      <motion.form
        initial={{ opacity: 0, y: 12, scale: 0.97 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.22, ease: 'easeOut' }}
        onSubmit={handleSubmit}
        className="w-full max-w-md rounded-3xl glass-card p-4"
      >
        <div className="flex items-center justify-between">
          <h2 className="text-base font-bold text-white">Create post</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <div className="mt-3 flex gap-3">
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-[#fff]">
            {(sessionUser?.display_name ?? '?').charAt(0).toUpperCase()}
          </span>
          <textarea
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder="Share something with your circle…  (use #tags for reach)"
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

            <button
              type="button"
              onClick={() => setBody((value) => `${value}${value && !value.endsWith(' ') ? ' ' : ''}#`)}
              className="btn-quiet px-3 py-1.5 text-xs"
              aria-label="Add hashtag"
            >
              # Hashtag
            </button>
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
    </div>
  )
}