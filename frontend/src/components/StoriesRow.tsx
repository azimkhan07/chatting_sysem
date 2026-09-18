import { AnimatePresence, motion } from 'framer-motion'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { storiesApi } from '@/lib/api'
import { userProfile } from '@/lib/paths'
import { useAuthStore } from '@/stores/authStore'
import type { Story, StoryGroup } from '@/types/story'
import type { User } from '@/types/user'

const SEEN_KEY = 'amtechat:seen-stories'
const RING = 'bg-gradient-to-tr from-amber-400 via-fuchsia-500 to-brand-400 p-[2.5px]'

function readSeen(): Set<number> {
  try {
    const raw = localStorage.getItem(SEEN_KEY)
    if (!raw) return new Set()
    return new Set(JSON.parse(raw) as number[])
  } catch {
    return new Set()
  }
}

function persistSeen(seen: Set<number>) {
  try {
    localStorage.setItem(SEEN_KEY, JSON.stringify([...seen]))
  } catch {
    // Storage unavailable — the session still marks stories as seen.
  }
}

export default function StoriesRow() {
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const [creating, setCreating] = useState(false)
  const [viewingUserId, setViewingUserId] = useState<number | null>(null)
  const [seen, setSeen] = useState<Set<number>>(readSeen)
  const [hover, setHover] = useState<{ group: StoryGroup; x: number; y: number } | null>(null)
  const hoverTimer = useRef<number | null>(null)

  const storiesQuery = useQuery({
    queryKey: ['stories'],
    queryFn: storiesApi.list,
    refetchInterval: 60_000,
  })

  useEffect(() => persistSeen(seen), [seen])

  const groups = storiesQuery.data?.stories ?? []
  const mine = me ? groups.find((group) => group.user.username === me.username) : undefined
  const others = groups.filter((group) => !(me && group.user.username === me.username))

  function markSeen(storyId: number) {
    setSeen((current) => {
      if (current.has(storyId)) return current
      const next = new Set(current)
      next.add(storyId)
      return next
    })
  }

  function showPreview(group: StoryGroup, event: React.PointerEvent<HTMLButtonElement>) {
    if (window.matchMedia('(pointer: coarse)').matches) return
    const rect = event.currentTarget.getBoundingClientRect()
    const cardWidth = 150
    const x = Math.min(
      Math.max(8, rect.left + rect.width / 2 - cardWidth / 2),
      Math.max(8, window.innerWidth - cardWidth - 8),
    )
    const y = Math.max(8, rect.top - 214)
    if (hoverTimer.current) window.clearTimeout(hoverTimer.current)
    hoverTimer.current = window.setTimeout(() => setHover({ group, x, y }), 150)
  }

  function hidePreview() {
    if (hoverTimer.current) window.clearTimeout(hoverTimer.current)
    setHover(null)
  }

  if (storiesQuery.isPending) {
    return (
      <div className="grid place-items-center py-6">
        <Spinner className="h-5 w-5" />
      </div>
    )
  }

  return (
    <>
      <div className="no-scrollbar -mx-1 flex items-start gap-3 overflow-x-auto px-1 py-1">
        <button
          type="button"
          onClick={() => {
            if (mine) {
              setViewingUserId(mine.user.id)
            } else {
              setCreating(true)
            }
          }}
          className="flex w-16 shrink-0 flex-col items-center gap-1.5"
          title={mine ? 'View your story' : 'Add a story'}
        >
          <span className="relative">
            {mine ? (
              <span className={`block rounded-full ${RING}`}>
                <span className="block h-[60px] w-[60px] overflow-hidden rounded-full bg-midnight-950 ring-2 ring-midnight-950">
                  <StoryMediaThumb story={mine.stories[mine.stories.length - 1]} />
                </span>
              </span>
            ) : (
              <span className="grid h-[60px] w-[60px] place-items-center rounded-full bg-slate-800/80 text-2xl font-light text-brand-300 transition group-hover:brightness-110">
                +
              </span>
            )}
            {!mine ? (
              <span className="absolute -right-0.5 -bottom-0.5 grid h-[18px] w-[18px] place-items-center rounded-full bg-brand-500 text-[10px] font-bold text-[#fff] ring-2 ring-midnight-950">
                +
              </span>
            ) : null}
          </span>
          <span className="text-[11px] text-slate-400">Your story</span>
        </button>

        {others.map((group) => {
          const unseen = group.stories.some((story) => !seen.has(story.id))
          return (
            <button
              key={group.user.id}
              type="button"
              onClick={() => setViewingUserId(group.user.id)}
              onPointerEnter={(event) => showPreview(group, event)}
              onPointerLeave={hidePreview}
              className="flex w-16 shrink-0 flex-col items-center gap-1.5"
            >
              <span
                className={`block rounded-full ${
                  unseen ? RING : 'bg-slate-700/80 p-[2.5px]'
                }`}
              >
                <span className="grid h-[58px] w-[58px] place-items-center rounded-full bg-midnight-950 text-lg font-bold text-brand-200 ring-2 ring-midnight-950">
                  {(group.user.display_name || group.user.username)
                    .charAt(0)
                    .toUpperCase()}
                </span>
              </span>
              <span className="max-w-[4.5rem] truncate text-[11px] text-slate-400">
                {group.user.username}
              </span>
            </button>
          )
        })}
      </div>

      <AnimatePresence>
        {hover ? <HoverPreview hover={hover} /> : null}
      </AnimatePresence>

      {creating ? (
        <CreateStoryModal
          onClose={() => setCreating(false)}
          onCreated={() => {
            setCreating(false)
            void queryClient.invalidateQueries({ queryKey: ['stories'] })
          }}
        />
      ) : null}

      {viewingUserId !== null ? (
        <StoryViewer
          groups={groups}
          initialUserId={viewingUserId}
          onSeen={markSeen}
          onClose={() => setViewingUserId(null)}
        />
      ) : null}
    </>
  )
}

function StoryMediaThumb({ story }: { story: Story }) {
  if (story.type === 'image') {
    return <img src={story.url} alt="" draggable={false} className="h-full w-full object-cover" />
  }
  return (
    <span className="grid h-full w-full place-items-center bg-gradient-to-br from-brand-500/30 to-fuchsia-500/30 text-lg font-bold text-brand-200">
      ▶
    </span>
  )
}

function HoverPreview({ hover }: { hover: { group: StoryGroup; x: number; y: number } }) {
  const story = hover.group.stories[hover.group.stories.length - 1]

  return (
    <motion.div
      initial={{ opacity: 0, y: 6, scale: 0.96 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      exit={{ opacity: 0, y: 6, scale: 0.96 }}
      transition={{ duration: 0.16, ease: 'easeOut' }}
      className="pointer-events-none fixed z-40 w-[150px] overflow-hidden rounded-2xl bg-black/90 shadow-2xl ring-1 ring-[rgba(255,255,255,0.2)] backdrop-blur"
      style={{
        left: hover.x,
        top: hover.y,
        transform: `translate(${0}px, ${0}px)`,
      }}
    >
      <div className="aspect-[3/4] w-full overflow-hidden">
        {story.type === 'video' ? (
          <video
            src={story.url}
            muted
            autoPlay
            loop
            playsInline
            className="h-full w-full object-cover"
          />
        ) : (
          <img src={story.url} alt="" className="h-full w-full object-cover" />
        )}
      </div>
      <div className="flex items-center gap-2 p-2">
        <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[9px] font-bold text-[#fff]">
          {(hover.group.user.display_name || hover.group.user.username).charAt(0).toUpperCase()}
        </span>
        <div className="min-w-0">
          <p className="truncate text-[11px] font-semibold text-[#fff]">
            {hover.group.user.display_name || hover.group.user.username}
          </p>
          <p className="text-[10px] text-[rgba(255,255,255,0.65)]">{relativeTime(story.created_at)}</p>
        </div>
      </div>
    </motion.div>
  )
}

interface CreateStoryModalProps {
  onClose: () => void
  onCreated: () => void
}

function CreateStoryModal({ onClose, onCreated }: CreateStoryModalProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [caption, setCaption] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    inputRef.current?.click()
  }, [])

  async function submit() {
    if (!file || saving) return
    setSaving(true)
    setError(null)
    try {
      await storiesApi.create({ media: file, caption })
      onCreated()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not post your story.')
      setSaving(false)
    }
  }

  const previewUrl = file ? URL.createObjectURL(file) : null

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm">
      <motion.div
        initial={{ opacity: 0, y: 12, scale: 0.97 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.22, ease: 'easeOut' }}
        className="w-full max-w-sm rounded-3xl glass-card p-5"
      >
        <div className="flex items-center justify-between">
          <h2 className="text-base font-bold text-white">New story</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <input
          ref={inputRef}
          type="file"
          accept="image/*,video/mp4,video/webm,video/quicktime"
          className="hidden"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
        />

        <div className="mt-4 aspect-[3/4] max-h-72 w-full overflow-hidden rounded-2xl bg-black">
          {previewUrl ? (
            file?.type.startsWith('video') ? (
              <video
                src={previewUrl}
                autoPlay
                muted
                loop
                playsInline
                className="h-full w-full object-contain"
              />
            ) : (
              <img src={previewUrl} alt="" className="h-full w-full object-contain" />
            )
          ) : (
            <div className="flex h-full w-full flex-col items-center justify-center gap-2 p-6 text-center">
              <span className="text-3xl text-brand-300">+</span>
              <p className="text-sm text-slate-400">
                Pick an image or a video — it disappears after 24 hours.
              </p>
            </div>
          )}
        </div>

        <input
          value={caption}
          onChange={(e) => setCaption(e.target.value)}
          maxLength={500}
          placeholder="Add a caption…"
          className="input-field mt-3"
        />

        {error ? <p className="mt-2 text-sm text-rose-300">{error}</p> : null}

        <button
          type="button"
          onClick={() => void submit()}
          disabled={!file || saving}
          className="btn-primary mt-4"
        >
          {saving ? 'Posting…' : 'Share'}
        </button>
      </motion.div>
    </div>
  )
}

interface FlatStory {
  owner: User
  story: Story
}

interface StoryViewerProps {
  groups: StoryGroup[]
  initialUserId: number
  onSeen?: (storyId: number) => void
  onClose: () => void
}

export function StoryViewer({ groups, initialUserId, onSeen, onClose }: StoryViewerProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)

  const items = useMemo<FlatStory[]>(
    () => groups.flatMap((group) => group.stories.map((story) => ({ owner: group.user, story }))),
    [groups],
  )

  const [localItems, setLocalItems] = useState<FlatStory[]>(items)
  const [index, setIndex] = useState(() => {
    const seen = readSeen()
    const owned = items
      .map((item, position) => ({ item, position }))
      .filter(({ item }) => item.owner.id === initialUserId)
    const firstUnseen = owned.find(({ item }) => !seen.has(item.story.id))
    return firstUnseen?.position ?? owned[0]?.position ?? 0
  })
  const [paused, setPaused] = useState(false)
  const [muted, setMuted] = useState(true)
  const [menuOpen, setMenuOpen] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const current = localItems[index]
  const isMine = me ? current?.owner.username === me.username : false

  useEffect(() => {
    if (current) onSeen?.(current.story.id)
  }, [index, current, onSeen])

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose()
      } else if (event.key === 'ArrowRight') {
        goNext()
      } else if (event.key === 'ArrowLeft') {
        goPrev()
      } else if (event.key === ' ') {
        event.preventDefault()
        setPaused((value) => !value)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  })

  useEffect(() => {
    if (!current || current.story.type !== 'image' || paused) return
    const timer = window.setTimeout(() => goNext(), 5000)
    return () => window.clearTimeout(timer)
  })

  function goNext() {
    setIndex((position) => {
      if (position < localItems.length - 1) return position + 1
      onClose()
      return position
    })
  }

  function goPrev() {
    setIndex((position) => Math.max(0, position - 1))
  }

  async function handleDelete() {
    if (!current || deleting) return
    if (!confirmDelete) {
      setConfirmDelete(true)
      return
    }
    setDeleting(true)
    try {
      await storiesApi.destroy(current.story.id)
      void queryClient.invalidateQueries({ queryKey: ['stories'] })
      const remaining = localItems.filter((item) => item.story.id !== current.story.id)
      if (remaining.length === 0) {
        onClose()
        return
      }
      setLocalItems(remaining)
      setIndex((position) => Math.max(0, Math.min(position, remaining.length - 1)))
      setMenuOpen(false)
      setConfirmDelete(false)
      setDeleting(false)
    } catch {
      setDeleting(false)
      setMenuOpen(false)
      setConfirmDelete(false)
    }
  }

  if (!current) return null

  const currentGroup = groups.find((group) => group.user.id === current.owner.id)
  const ownerStories = currentGroup?.stories ?? []
  const storyPosition = ownerStories.findIndex((story) => story.id === current.story.id)
  const pause = paused || deleting

  return (
    <div className="fixed inset-0 z-50 bg-black">
      <div className="relative mx-auto h-full w-full sm:max-w-[430px]">
        <div className="absolute inset-x-0 top-0 z-20 p-3">
          <div className="flex gap-1.5">
            {ownerStories.map((story, position) => (
              <span
                key={story.id}
                className={`h-0.5 flex-1 rounded-full ${
                  position <= storyPosition ? 'bg-[#fff]' : 'bg-[rgba(255,255,255,0.3)]'
                }`}
              />
            ))}
          </div>
          <div className="mt-3 flex items-center gap-3">
            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-[#fff]">
              {(current.owner.display_name || current.owner.username).charAt(0).toUpperCase()}
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-[#fff]">
                {current.owner.display_name || current.owner.username}
              </p>
              <p className="text-xs text-[rgba(255,255,255,0.65)]">{relativeTime(current.story.created_at)}</p>
            </div>

            <div className="ml-auto flex items-center gap-1">
              {current.story.type === 'video' ? (
                <button
                  type="button"
                  onClick={() => setMuted((value) => !value)}
                  className="grid h-8 w-8 place-items-center rounded-lg text-[rgba(255,255,255,0.8)] transition hover:bg-[rgba(255,255,255,0.12)]"
                  aria-label={muted ? 'Unmute' : 'Mute'}
                >
                  <MuteIcon muted={muted} />
                </button>
              ) : null}
              <div className="relative">
                <button
                  type="button"
                  onClick={() => {
                    setMenuOpen((value) => !value)
                    setConfirmDelete(false)
                  }}
                  className="grid h-8 w-8 place-items-center rounded-lg text-lg leading-none text-[rgba(255,255,255,0.8)] transition hover:bg-[rgba(255,255,255,0.12)]"
                  aria-label="Story options"
                >
                  …
                </button>
                <AnimatePresence>
                  {menuOpen ? (
                    <motion.div
                      initial={{ opacity: 0, y: -4, scale: 0.98 }}
                      animate={{ opacity: 1, y: 0, scale: 1 }}
                      exit={{ opacity: 0, y: -4, scale: 0.98 }}
                      transition={{ duration: 0.14, ease: 'easeOut' }}
                      className="absolute top-9 right-0 w-44 overflow-hidden rounded-xl bg-zinc-800/95 p-1 shadow-2xl backdrop-blur"
                    >
                      <button
                        type="button"
                        onClick={() => {
                          onClose()
                          navigate(userProfile(current.owner.username))
                        }}
                        className="w-full rounded-lg px-3 py-2 text-left text-sm text-[rgba(255,255,255,0.9)] transition hover:bg-[rgba(255,255,255,0.12)]"
                      >
                        View profile
                      </button>
                      {isMine ? (
                        <button
                          type="button"
                          onClick={() => void handleDelete()}
                          disabled={deleting}
                          className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                            confirmDelete
                              ? 'font-semibold text-[#fff]'
                              : 'text-[rgba(255,255,255,0.9)] hover:bg-[rgba(255,255,255,0.12)]'
                          } ${confirmDelete ? 'bg-rose-600/80' : ''}`}
                        >
                          {deleting
                            ? 'Deleting…'
                            : confirmDelete
                              ? 'Confirm delete?'
                              : 'Delete story'}
                        </button>
                      ) : null}
                    </motion.div>
                  ) : null}
                </AnimatePresence>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="grid h-8 w-8 place-items-center rounded-lg text-[rgba(255,255,255,0.8)] transition hover:bg-[rgba(255,255,255,0.12)]"
                aria-label="Close"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <div className="flex h-full items-center justify-center">
          {current.story.type === 'video' ? (
            <VideoStory
              story={current.story}
              muted={muted}
              playing={!pause}
              onEnded={goNext}
            />
          ) : (
            <img
              src={current.story.url}
              alt=""
              draggable={false}
              className={`max-h-full w-full object-contain ${pause ? 'opacity-90' : ''}`}
            />
          )}
        </div>

        {current.story.caption ? (
          <p className="absolute inset-x-0 bottom-16 z-20 px-6 text-center text-sm text-[#fff] [text-shadow:0_1px_3px_rgba(0,0,0,0.75)]">
            {current.story.caption}
          </p>
        ) : null}

        {pause && current.story.type === 'image' ? (
          <span className="absolute inset-x-0 bottom-16 z-20 mx-auto w-fit rounded-full bg-[rgba(0,0,0,0.5)] px-3 py-1 text-xs text-[#fff] backdrop-blur">
            Paused
          </span>
        ) : null}

        <div
          className="absolute inset-0"
          onPointerDown={() => setPaused(true)}
          onPointerUp={() => setPaused(false)}
          onPointerLeave={() => setPaused(false)}
        >
          <button
            type="button"
            aria-label="Previous story"
            className="absolute inset-y-0 left-0 z-10 w-1/3"
            onClick={goPrev}
          />
          <button
            type="button"
            aria-label="Next story"
            className="absolute inset-y-0 right-0 z-10 w-2/3"
            onClick={goNext}
          />
        </div>
      </div>
    </div>
  )
}

function VideoStory({
  story,
  muted,
  playing,
  onEnded,
}: {
  story: Story
  muted: boolean
  playing: boolean
  onEnded: () => void
}) {
  const ref = useRef<HTMLVideoElement>(null)

  useEffect(() => {
    const video = ref.current
    if (!video) return
    video.muted = muted
    if (playing) {
      video.currentTime = 0
      void video.play()
    } else {
      video.pause()
    }
  }, [playing, muted, story.id])

  return (
    <video
      ref={ref}
      src={story.url}
      autoPlay
      playsInline
      muted
      onEnded={onEnded}
      className="max-h-full w-full object-contain"
    />
  )
}

function MuteIcon({ muted }: { muted: boolean }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" aria-hidden="true">
      <path
        d="M4 9v6h4l5 4V5L8 9H4Z"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinejoin="round"
      />
      {muted ? (
        <path d="m16 9 5 6M21 9l-5 6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
      ) : (
        <>
          <path d="M16.5 8.5a5 5 0 0 1 0 7" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
          <path d="M18.5 6.5a8.5 8.5 0 0 1 0 11" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </>
      )}
    </svg>
  )
}

function relativeTime(value: string): string {
  const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h`
  return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}