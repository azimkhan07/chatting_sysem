import { useMutation, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useMemo, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import MentionPicker from '@/components/composer/MentionPicker'
import SongPicker from '@/components/composer/SongPicker'
import { findActiveMention, useTrackedTextarea } from '@/hooks/useMentionTrigger'
import { postsApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { Post } from '@/types/post'
import type { Song } from '@/types/song'
import type { MentionSuggestion } from '@/types/user'

const MAX_POST_LENGTH = 5000
const ACCEPTED_EXTENSIONS = /\.(jpe?g|png|webp|gif|mp4|webm|mov)$/i
const MAX_LOCATION_LENGTH = 255

interface PendingMedia {
  file: File
  previewUrl: string
}

type Panel = 'music' | 'location' | 'tag' | null

interface PostComposerModalProps {
  onClose: () => void
}

export default function PostComposerModal({ onClose }: PostComposerModalProps) {
  const queryClient = useQueryClient()
  const sessionUser = useAuthStore((state) => state.user)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const locationInputRef = useRef<HTMLInputElement>(null)
  const caption = useTrackedTextarea()
  const [media, setMedia] = useState<PendingMedia[]>([])
  const [song, setSong] = useState<Song | null>(null)
  const [location, setLocation] = useState('')
  const [panel, setPanel] = useState<Panel>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const urls = media.map((item) => item.previewUrl)
    return () => urls.forEach((url) => URL.revokeObjectURL(url))
  }, [media])

  const createPost = useMutation({
    mutationFn: (form: {
      body: string
      media: File[]
      location: string | null
      songId: number | null
    }) => postsApi.create(form),
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

  /**
   * Splices `@name` into the caption in place of the half-typed fragment.
   *
   * The mention is inserted as text, not submitted as a separate id: the
   * server parses the body, so a tag that is not in the caption does not
   * exist. That also means an unresolvable name is harmless — it stays as
   * ordinary text.
   */
  function insertMention(user: MentionSuggestion) {
    const active = findActiveMention(caption.text, caption.caret)
    if (!active) return

    const before = caption.text.slice(0, active.start)
    const after = caption.text.slice(active.end)
    // Trailing space so the next word does not run into the handle.
    const inserted = `@${user.username} `
    const next = `${before}${inserted}${after}`

    caption.setTextWithCaret(next, before.length + inserted.length)
    setPanel(null)
  }

  function togglePanel(next: Exclude<Panel, null>) {
    setPanel((current) => (current === next ? null : next))
  }

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    const text = caption.text.trim()
    if (createPost.isPending) return
    if (!text && media.length === 0) return
    if (text.length > MAX_POST_LENGTH) return

    const place = location.trim()
    createPost.mutate({
      body: text,
      media: media.map((item) => item.file),
      // Sent as null rather than "" so the server stores an absent location
      // instead of a blank place line.
      location: place === '' ? null : place,
      songId: song?.id ?? null,
    })
  }

  // The tag panel is driven by the text at the caret, not by a button press:
  // typing "@" anywhere in the caption should open it, and a `@` in an email
  // address should not.
  const activeMention = useMemo(
    () => findActiveMention(caption.text, caption.caret),
    [caption.text, caption.caret],
  )

  const canPost = caption.text.trim().length > 0 || media.length > 0
  const showTagPanel = panel === 'tag' && activeMention !== null

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm">
      <motion.form
        initial={{ opacity: 0, y: 12, scale: 0.97 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.22, ease: 'easeOut' }}
        onSubmit={handleSubmit}
        className="flex max-h-[90vh] w-full max-w-md flex-col rounded-3xl glass-card p-4"
      >
        <div className="flex shrink-0 items-center justify-between">
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
            ref={caption.ref}
            value={caption.text}
            onChange={caption.onChange}
            onSelect={caption.onSelect}
            onKeyUp={caption.onKeyUp}
            onClick={caption.onClick}
            placeholder="Share something with your circle…  (@tag someone, #tags for reach)"
            rows={3}
            className="min-h-[4.5rem] w-full resize-none bg-transparent text-sm text-slate-100 placeholder:text-slate-500 outline-none"
            aria-label="Post body"
            aria-expanded={showTagPanel}
          />
        </div>

        {showTagPanel ? (
          <div className="mt-2">
            <MentionPicker term={activeMention.term} onPick={insertMention} />
          </div>
        ) : null}

        <div className="min-h-0 flex-1 overflow-y-auto">
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

          {panel === 'music' ? (
            <div className="mt-3">
              <SongPicker
                picked={song}
                onPick={setSong}
                onError={setError}
              />
            </div>
          ) : null}

          {panel === 'location' ? (
            <div className="mt-3">
              <input
                ref={locationInputRef}
                autoFocus
                value={location}
                maxLength={MAX_LOCATION_LENGTH}
                onChange={(event) => setLocation(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    event.preventDefault()
                    setPanel(null)
                  }
                }}
                placeholder="Add a place"
                aria-label="Post location"
                className="w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
              />
              <p className="mt-1 text-[11px] text-slate-500">
                A place name, not your exact position. {location.length}/{MAX_LOCATION_LENGTH}
              </p>
            </div>
          ) : null}
        </div>

        <div className="mt-2 flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-white/5 pt-3">
          <div className="flex flex-wrap items-center gap-2">
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
              onClick={() => togglePanel('music')}
              className={`btn-quiet px-3 py-1.5 text-xs ${song ? 'ring-2 ring-brand-400' : ''}`}
              aria-pressed={panel === 'music'}
            >
              {song ? `♪ ${song.name}` : '♫ Music'}
            </button>

            <button
              type="button"
              onClick={() => togglePanel('location')}
              className={`btn-quiet px-3 py-1.5 text-xs ${
                location.trim() ? 'ring-2 ring-brand-400' : ''
              }`}
              aria-pressed={panel === 'location'}
            >
              {location.trim() ? `⌖ ${location.trim()}` : '⌖ Location'}
            </button>

            <button
              type="button"
              onClick={() => {
                togglePanel('tag')
                // Puts the caret after a fresh "@" so the picker has something
                // to show immediately instead of an empty panel.
                if (panel !== 'tag') appendMentionSeed()
              }}
              className={`btn-quiet px-3 py-1.5 text-xs ${panel === 'tag' ? 'ring-2 ring-brand-400' : ''}`}
              aria-pressed={panel === 'tag'}
            >
              @ Tag
            </button>
          </div>
          <button
            type="submit"
            disabled={!canPost || caption.text.length > MAX_POST_LENGTH || createPost.isPending}
            className="btn-primary w-auto px-4 py-2"
          >
            {createPost.isPending ? <Spinner className="h-4 w-4" /> : null}
            {createPost.isPending ? 'Posting…' : 'Post'}
          </button>
        </div>

        {createPost.isError || error ? (
          <p className="mt-2 shrink-0 text-xs text-rose-400">{createPost.error?.message ?? error}</p>
        ) : null}
      </motion.form>
    </div>
  )

  /**
   * Appends a space plus `@` and drops the caret right after it, so opening the
   * tag panel by button has something to show instead of an empty box.
   */
  function appendMentionSeed() {
    const text = caption.text
    const needsSpace = text.length > 0 && !/\s$/.test(text)
    const next = `${text}${needsSpace ? ' ' : ''}@`
    caption.setTextWithCaret(next, next.length)
  }
}
