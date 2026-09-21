import { AnimatePresence, motion } from 'framer-motion'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import StoryComposer from '@/components/StoryComposer'
import { resolveTextStyle, textPosition } from '@/components/StoryComposer'
import { storiesApi } from '@/lib/api'
import { hashtagPage, userProfile } from '@/lib/paths'
import { filterCss } from '@/lib/storyFilters'
import { useAuthStore } from '@/stores/authStore'
import type { Song } from '@/types/song'
import type { Story, StoryGroup } from '@/types/story'
import type { User } from '@/types/user'

const SEEN_KEY = 'amtechat:seen-stories'

function playableSongUrl(song: Song): string {
  return song.stream_url ?? song.url
}

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
      <div className="no-scrollbar -mx-1 flex items-start gap-2 overflow-x-auto px-1 py-1">
        <div className="flex w-[4.25rem] shrink-0 flex-col items-center gap-1.5">
          <span className="relative">
            <button
              type="button"
              onClick={() => {
                if (mine) {
                  setViewingUserId(mine.user.id)
                } else {
                  setCreating(true)
                }
              }}
              className="block rounded-full"
              title={mine ? 'View your story' : 'Add a story'}
            >
              {mine ? (
                <StoryAvatar user={mine.user} ring="unseen" size={58} />
              ) : (
                <span className="story-ring-slate block p-[2.5px]">
                  <span className="grid h-[54px] w-[54px] place-items-center rounded-full bg-slate-800/80 text-2xl font-light text-brand-300">
                    +
                  </span>
                </span>
              )}
            </button>
            {mine ? (
              <button
                type="button"
                onClick={() => setCreating(true)}
                aria-label="Add a story"
                className="absolute -bottom-1 right-0 grid h-[18px] w-[18px] place-items-center rounded-full bg-[#fff] text-[11px] font-bold leading-none text-brand-600 shadow-md ring-2 ring-midnight-950 transition hover:scale-110 active:scale-95"
              >
                +
              </button>
            ) : null}
          </span>
          <span className="max-w-[4.5rem] truncate text-[11px] text-slate-400">Your story</span>
        </div>

        {others.map((group) => {
          const unseen = group.stories.some((story) => !seen.has(story.id))
          return (
            <button
              key={group.user.id}
              type="button"
              onClick={() => setViewingUserId(group.user.id)}
              onPointerEnter={(event) => showPreview(group, event)}
              onPointerLeave={hidePreview}
              className="flex w-[4.25rem] shrink-0 flex-col items-center gap-1.5"
            >
              <StoryAvatar user={group.user} ring={unseen ? 'unseen' : 'seen'} size={56} />
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
        <StoryComposer
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

function StoryAvatar({ user, ring, size = 56 }: { user: User; ring: 'unseen' | 'seen'; size?: number }) {
  const label = (user.display_name || user.username).charAt(0).toUpperCase()
  return (
    <span className={`block ${ring === 'unseen' ? 'story-ring' : 'story-ring-seen'} p-[2.5px]`}>
      <span
        className="block overflow-hidden rounded-full bg-midnight-950 ring-2 ring-midnight-950"
        style={{ width: size, height: size }}
      >
        {user.avatar_url ? (
          <img src={user.avatar_url} alt="" loading="lazy" className="h-full w-full object-cover" />
        ) : (
          <span className="grid h-full w-full place-items-center text-lg font-bold text-brand-200">
            {label}
          </span>
        )}
      </span>
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
            style={{ filter: filterCss(story.effects) }}
            className="h-full w-full object-cover"
          />
        ) : (
          <img
            src={story.url}
            alt=""
            style={{ filter: filterCss(story.effects) }}
            className="h-full w-full object-cover"
          />
        )}
      </div>
      <div className="flex items-center gap-2 p-2">
        <span className="grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[9px] font-bold text-[#fff]">
          {hover.group.user.avatar_url ? (
            <img src={hover.group.user.avatar_url} alt="" className="h-full w-full object-cover" />
          ) : (
            (hover.group.user.display_name || hover.group.user.username).charAt(0).toUpperCase()
          )}
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
  const [songPlaying, setSongPlaying] = useState(false)
  const [videoProgress, setVideoProgress] = useState(0)
  const [menuOpen, setMenuOpen] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const audioRef = useRef<HTMLAudioElement>(null)

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

  useEffect(() => {
    setVideoProgress(0)
    const audio = audioRef.current
    if (!audio || !current?.story.song) {
      setSongPlaying(false)
      return
    }
    audio.volume = 1
    audio.muted = false
    audio.preload = 'auto'
    if (songPlaying) {
      audio.currentTime = 0
      void audio.play().catch(() => setSongPlaying(false))
    } else {
      audio.pause()
    }
  }, [current?.story.id, current?.story.song?.url, current?.story.song?.stream_url, songPlaying])

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
                className="h-0.5 flex-1 overflow-hidden rounded-full bg-[rgba(255,255,255,0.3)]"
              >
                {position < storyPosition ? (
                  <span className="block h-full w-full rounded-full bg-[#fff]" />
                ) : null}
                {position === storyPosition ? (
                  story.type === 'video' ? (
                    <span className="story-progress" style={{ width: `${videoProgress * 100}%`, animation: 'none' }} />
                  ) : (
                    <span className={`story-progress ${pause ? 'paused' : ''}`} style={{ animationDuration: '5s' }} />
                  )
                ) : null}
              </span>
            ))}
          </div>
          <div className="mt-3 flex items-center gap-3">
            <span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-[#fff]">
              {current.owner.avatar_url ? (
                <img src={current.owner.avatar_url} alt="" className="h-full w-full object-cover" />
              ) : (
                (current.owner.display_name || current.owner.username).charAt(0).toUpperCase()
              )}
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-[#fff]">
                {current.owner.display_name || current.owner.username}
              </p>
              <p className="text-xs text-[rgba(255,255,255,0.65)]">{relativeTime(current.story.created_at)}</p>
            </div>

            <div className="ml-auto flex items-center gap-1">
              {current.story.song ? (
                <button
                  type="button"
                  onClick={() => setSongPlaying((value) => !value)}
                  className="grid h-8 w-8 place-items-center rounded-lg text-[rgba(255,255,255,0.8)] transition hover:bg-[rgba(255,255,255,0.12)]"
                  aria-label={songPlaying ? 'Mute story music' : 'Play story music'}
                >
                  <MuteIcon muted={!songPlaying} />
                </button>
              ) : null}
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
              onProgress={setVideoProgress}
            />
          ) : (
            <img
              src={current.story.url}
              alt=""
              draggable={false}
              style={{ filter: filterCss(current.story.effects) }}
              className={`max-h-full w-full object-contain ${pause ? 'opacity-90' : ''}`}
            />
          )}
        </div>

        {current.story.caption ? (
          <div
            className="absolute inset-x-0 z-20 flex px-6 pb-14"
            style={{
              ...textPosition(current.story.text_style?.pos),
              justifyContent:
                current.story.text_style?.align === 'left'
                  ? 'flex-start'
                  : current.story.text_style?.align === 'right'
                    ? 'flex-end'
                    : 'center',
            }}
          >
            <p style={resolveTextStyle(current.story.text_style)} className="max-w-full break-words">
              {renderCaption(current.story.caption, navigate)}
            </p>
          </div>
        ) : null}

        {current.story.song ? (
          <button
            type="button"
            onClick={() => setSongPlaying((value) => !value)}
            className="absolute bottom-24 left-4 z-20 transition active:scale-95"
            aria-label={songPlaying ? 'Pause story music' : 'Play story music'}
          >
            <MusicSticker song={current.story.song} muted={!songPlaying} />
          </button>
        ) : null}

        {current.story.song ? (
          <audio
            ref={audioRef}
            src={playableSongUrl(current.story.song)}
            preload="auto"
            onError={() => setSongPlaying(false)}
            onEnded={() => setSongPlaying(false)}
          />
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
  onProgress,
}: {
  story: Story
  muted: boolean
  playing: boolean
  onEnded: () => void
  onProgress?: (value: number) => void
}) {
  const ref = useRef<HTMLVideoElement>(null)

  useEffect(() => {
    const video = ref.current
    if (!video) return
    video.muted = muted
    if (playing) {
      video.currentTime = 0
      void video.play().catch(() => undefined)
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
      onTimeUpdate={(event) => {
        const video = event.currentTarget
        if (video.duration) onProgress?.(video.currentTime / video.duration)
      }}
      style={{ filter: filterCss(story.effects) }}
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

function renderCaption(caption: string, navigate: (path: string) => void) {
  const tokens = caption.split(/(@[A-Za-z0-9_.]+|#[A-Za-z0-9_]+)/g)
  return tokens.map((token, index) => {
    if (token.startsWith('@')) {
      return (
        <button
          key={index}
          type="button"
          onClick={() => navigate(userProfile(token.slice(1)))}
          className="font-semibold text-sky-300 hover:underline"
        >
          {token}
        </button>
      )
    }
    if (token.startsWith('#')) {
      return (
        <button
          key={index}
          type="button"
          onClick={() => navigate(hashtagPage(token.slice(1)))}
          className="font-semibold text-amber-300 hover:underline"
        >
          {token}
        </button>
      )
    }
    return <span key={index}>{token}</span>
  })
}

function MusicSticker({ song, muted }: { song: Song; muted: boolean }) {
  return (
    <div className="flex items-center gap-2 rounded-full bg-[rgba(0,0,0,0.55)] py-1 pr-3 pl-2 backdrop-blur">
      <NoteWaveIcon className="h-5 w-5 shrink-0 text-brand-300" />
      <div className="w-36 overflow-hidden">
        <Marquee text={`♪ ${song.name} — ${song.artist}`} />
      </div>
      <span className="shrink-0 text-[10px] text-[rgba(255,255,255,0.8)]">
        {muted ? '🔇' : '🔊'}
      </span>
    </div>
  )
}

function NoteWaveIcon({ className }: { className: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
      <path d="M9 4.5v11.55a3 3 0 1 0 1.5 2.6V7.2l8-2v9.85a3 3 0 1 0 1.5 2.6V4l-10.5 2.5Z" />
    </svg>
  )
}

function Marquee({ text }: { text: string }) {
  const [move, setMove] = useState(false)
  useEffect(() => {
    const timer = window.setTimeout(() => setMove(true), 800)
    return () => window.clearTimeout(timer)
  }, [])
  return (
    <span className="block overflow-hidden text-nowrap text-[11px] font-semibold text-[#fff]">
      <span
        className={`inline-block ${move ? 'marquee-anim' : ''}`}
        style={{ paddingRight: '2rem' }}
      >
        {text}
      </span>
    </span>
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