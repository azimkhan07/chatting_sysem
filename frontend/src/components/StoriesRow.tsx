import { AnimatePresence, motion } from 'framer-motion'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { songsApi, storiesApi, usersApi } from '@/lib/api'
import { hashtagPage, userProfile } from '@/lib/paths'
import { filterCss, STORY_FILTERS } from '@/lib/storyFilters'
import { useAuthStore } from '@/stores/authStore'
import type { Song } from '@/types/song'
import type { Story, StoryGroup } from '@/types/story'
import type { User } from '@/types/user'

const SEEN_KEY = 'amtechat:seen-stories'
const RING = 'bg-gradient-to-tr from-amber-400 via-fuchsia-500 to-brand-400 p-[2.5px]'
const EMOJI = [
  '😀', '😂', '😍', '😎', '🥳', '😜', '🤩', '😇',
  '❤️', '🔥', '✨', '💯', '👍', '👏', '🎉', '🎶',
  '🌅', '🌙', '🌈', '⚡', '🚀', '💪', '🙌', '💫',
]

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
        <div className="flex w-16 shrink-0 flex-col items-center gap-1.5">
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
                <span className={`block rounded-full ${RING}`}>
                  <span className="block h-[60px] w-[60px] overflow-hidden rounded-full bg-midnight-950 ring-2 ring-midnight-950">
                    <StoryMediaThumb story={mine.stories[mine.stories.length - 1]} />
                  </span>
                </span>
              ) : (
                <span className="grid h-[60px] w-[60px] place-items-center rounded-full bg-slate-800/80 text-2xl font-light text-brand-300">
                  +
                </span>
              )}
            </button>
            {mine ? (
              <button
                type="button"
                onClick={() => setCreating(true)}
                aria-label="Add a story"
                className="absolute -bottom-1 right-0 grid h-[18px] w-[18px] place-items-center rounded-full bg-[#fff] text-[11px] font-bold leading-none text-brand-600 shadow-md ring-2 ring-[#0d1324] transition hover:scale-110 active:scale-95"
              >
                +
              </button>
            ) : null}
          </span>
          <span className="text-[11px] text-slate-400">Your story</span>
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
    return (
      <img
        src={story.url}
        alt=""
        draggable={false}
        style={{ filter: filterCss(story.effects) }}
        className="h-full w-full object-cover"
      />
    )
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
  const [effects, setEffects] = useState('none')
  const [songId, setSongId] = useState<number | null>(null)
  const [songs, setSongs] = useState<Song[]>([])
  const [showSongs, setShowSongs] = useState(false)
  const [genreFilter, setGenreFilter] = useState('All')
  const [showEmoji, setShowEmoji] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [mentionTerm, setMentionTerm] = useState<string | null>(null)
  const [suggestions, setSuggestions] = useState<User[]>([])
  const [searchingMentions, setSearchingMentions] = useState(false)
  const debounceRef = useRef<number | null>(null)

  useEffect(() => {
    inputRef.current?.click()
    void songsApi
      .list()
      .then((data) => setSongs(data.songs))
      .catch(() => setSongs([]))
    return () => {
      if (debounceRef.current) window.clearTimeout(debounceRef.current)
    }
  }, [])

  const previewUrl = file ? URL.createObjectURL(file) : null
  const isVideo = Boolean(file?.type.startsWith('video'))

  function updateCaption(value: string) {
    setCaption(value)
    const term = value.match(/(?:^|\s)@([A-Za-z0-9_.]*)$/)?.[1] ?? null
    setMentionTerm(term)

    if (debounceRef.current) window.clearTimeout(debounceRef.current)
    if (!term) {
      setSuggestions([])
      setSearchingMentions(false)
      return
    }
    if (term.length < 1) {
      setSuggestions([])
      setSearchingMentions(false)
      return
    }
    debounceRef.current = window.setTimeout(() => {
      setSearchingMentions(true)
      void usersApi
        .search(term)
        .then((data) => setSuggestions(data.users))
        .catch(() => setSuggestions([]))
        .finally(() => setSearchingMentions(false))
    }, 200)
  }

  function pickMention(user: User) {
    const match = caption.match(/(?:^|\s)@[A-Za-z0-9_.]*$/)
    if (!match) {
      setCaption(`${caption}@${user.username} `)
    } else {
      const prefix = match[0].startsWith(' ') ? ' ' : ''
      setCaption(caption.slice(0, caption.length - match[0].length) + `${prefix}@${user.username} `)
    }
    setMentionTerm(null)
    setSuggestions([])
  }

  async function submit() {
    if (!file || saving) return
    setSaving(true)
    setError(null)
    try {
      await storiesApi.create({
        media: file,
        caption: caption.trim(),
        effects,
        songId,
      })
      onCreated()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not post your story.')
      setSaving(false)
    }
  }

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
          onChange={(e) => {
            setFile(e.target.files?.[0] ?? null)
            setEffects('none')
          }}
        />

        <div className="relative mt-4 aspect-[3/4] max-h-72 w-full overflow-hidden rounded-2xl bg-black">
          {previewUrl ? (
            isVideo ? (
              <video
                src={previewUrl}
                autoPlay
                muted
                loop
                playsInline
                className="h-full w-full object-contain"
              />
            ) : (
              <img
                src={previewUrl}
                alt=""
                style={{ filter: filterCss(effects) }}
                className="h-full w-full object-contain"
              />
            )
          ) : (
            <div className="flex h-full w-full flex-col items-center justify-center gap-2 p-6 text-center">
              <span className="text-3xl text-brand-300">+</span>
              <p className="text-sm text-slate-400">
                Pick an image or a video — it disappears after 24 hours.
              </p>
            </div>
          )}
          {songId ? (
            <div className="pointer-events-none absolute right-0 bottom-0 left-0 flex items-center gap-1.5 bg-gradient-to-t from-black/80 to-transparent px-3 pt-5 pb-2">
              <MusicNoteIcon className="h-3.5 w-3.5 shrink-0 text-brand-300" />
              <span className="truncate text-[11px] font-semibold text-[#fff]">
                <Marquee text={`${songName(songs, songId)}`} />
              </span>
            </div>
          ) : null}
        </div>

        {file && !isVideo ? (
          <div className="no-scrollbar mt-3 flex gap-2 overflow-x-auto pb-0.5">
            {STORY_FILTERS.map((filter) => {
              const active = effects === filter.key
              return (
                <button
                  key={filter.key}
                  type="button"
                  onClick={() => setEffects(filter.key)}
                  className={`flex shrink-0 flex-col items-center gap-1 rounded-lg p-0.5 transition ${
                    active ? 'ring-2 ring-brand-400' : 'ring-1 ring-white/10 hover:ring-white/30'
                  }`}
                >
                  <img
                    src={previewUrl ?? ''}
                    alt=""
                    style={{ filter: filter.css }}
                    className="h-10 w-10 rounded-md object-cover"
                  />
                  <span className={`text-[9px] ${active ? 'text-brand-300' : 'text-slate-400'}`}>
                    {filter.name}
                  </span>
                </button>
              )
            })}
          </div>
        ) : null}

        <div className="relative">
          <input
            value={caption}
            onChange={(e) => updateCaption(e.target.value)}
            maxLength={500}
            placeholder="Add a caption…  (use @name to mention, #tag for hashtags)"
            className="input-field mt-3"
          />

          {mentionTerm ? (
            <div className="absolute inset-x-0 top-full z-10 mt-1 max-h-56 overflow-y-auto rounded-xl bg-zinc-800/95 p-1 shadow-2xl backdrop-blur">
              {searchingMentions ? (
                <div className="flex items-center justify-center gap-2 px-3 py-2 text-xs text-[rgba(255,255,255,0.65)]">
                  <Spinner className="h-3 w-3" />
                  Searching…
                </div>
              ) : suggestions.length === 0 ? (
                <p className="px-3 py-2 text-xs text-[rgba(255,255,255,0.65)]">
                  No one found — keep typing…
                </p>
              ) : (
                suggestions.map((user) => (
                  <button
                    key={user.id}
                    type="button"
                    onClick={() => pickMention(user)}
                    className="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition hover:bg-[rgba(255,255,255,0.12)]"
                  >
                    <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[10px] font-bold text-[#fff]">
                      {(user.display_name || user.username).charAt(0).toUpperCase()}
                    </span>
                    <span className="min-w-0">
                      <span className="block truncate text-xs font-semibold text-[#fff]">
                        {user.display_name || user.username}
                      </span>
                      <span className="block truncate text-[11px] text-[rgba(255,255,255,0.65)]">
                        @{user.username}
                      </span>
                    </span>
                  </button>
                ))
              )}
            </div>
          ) : null}
        </div>

        <div className="mt-3 flex items-center gap-2">
          <button
            type="button"
            onClick={() => {
              setShowSongs((value) => !value)
              setShowEmoji(false)
            }}
            className={`btn-quiet px-3 py-1.5 text-xs ${songId ? 'ring-2 ring-brand-400' : ''}`}
            aria-expanded={showSongs}
          >
            🎵 {songId ? songName(songs, songId) : 'Add music'}
          </button>
          <button
            type="button"
            onClick={() => {
              setShowEmoji((value) => !value)
              setShowSongs(false)
              setGenreFilter('All')
            }}
            className="btn-quiet px-3 py-1.5 text-xs"
            aria-expanded={showEmoji}
          >
            😀 Sticker
          </button>
        </div>

        {showSongs ? (
          <div className="mt-2 rounded-xl bg-white/5 p-1.5">
            <div className="mb-1.5 flex gap-1.5 overflow-x-auto border-b border-white/10 pb-1.5">
              {['All', ...Array.from(new Set(songs.map((song) => song.genre).filter(Boolean) as string[]))].map(
                (genre) => {
                  const active = genreFilter === genre
                  return (
                    <button
                      key={genre}
                      type="button"
                      onClick={() => setGenreFilter(active ? 'All' : genre)}
                      className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium transition ${
                        active ? 'bg-brand-500/80 text-white' : 'bg-white/10 text-slate-300 hover:bg-white/20'
                      }`}
                    >
                      {genre}
                    </button>
                  )
                },
              )}
            </div>
            <div className="max-h-32 space-y-1 overflow-y-auto">
              {songs.length === 0 ? (
                <p className="px-2 py-3 text-center text-xs text-slate-500">
                  Music library is empty right now.
                </p>
              ) : (
                songs
                  .filter((song) => genreFilter === 'All' || song.genre === genreFilter)
                  .map((song) => {
                    const active = songId === song.id
                    return (
                      <button
                        key={song.id}
                        type="button"
                        onClick={() => setSongId(active ? null : song.id)}
                        className={`flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition ${
                          active ? 'bg-brand-500/25 ring-1 ring-brand-400/60' : 'hover:bg-white/10'
                        }`}
                      >
                        <MusicNoteIcon className="h-4 w-4 shrink-0 text-brand-300" />
                        <span className="min-w-0">
                          <span className="block truncate text-xs font-semibold text-slate-200">
                            {song.name}
                          </span>
                          <span className="block truncate text-[11px] text-slate-500">
                            {song.artist}
                            {song.genre ? ` · ${song.genre}` : ''}
                          </span>
                        </span>
                        {active ? <span className="ml-auto text-xs text-brand-300">✓</span> : null}
                      </button>
                    )
                  })
              )}
            </div>
          </div>
        ) : null}

        {showEmoji ? (
          <div className="mt-2 grid grid-cols-8 gap-1 rounded-xl bg-white/5 p-2">
            {EMOJI.map((emoji) => (
              <button
                key={emoji}
                type="button"
                onClick={() => setCaption((value) => `${value}${value && !value.endsWith(' ') ? ' ' : ''}${emoji} `)}
                className="grid h-8 w-8 place-items-center rounded-lg text-lg transition hover:bg-white/10 active:scale-90"
                aria-label={`Add ${emoji}`}
              >
                {emoji}
              </button>
            ))}
          </div>
        ) : null}

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
  const [songPlaying, setSongPlaying] = useState(false)
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
              style={{ filter: filterCss(current.story.effects) }}
              className={`max-h-full w-full object-contain ${pause ? 'opacity-90' : ''}`}
            />
          )}
        </div>

        {current.story.caption ? (
          <p className="absolute inset-x-0 bottom-16 z-20 px-6 text-center text-sm text-[#fff] [text-shadow:0_1px_3px_rgba(0,0,0,0.75)]">
            {renderCaption(current.story.caption, navigate)}
          </p>
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

        {current.story.song && songPlaying ? (
          <audio
            key={current.story.id}
            src={current.story.song.url}
            autoPlay
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

function MusicNoteIcon({ className }: { className: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" className={className} aria-hidden="true">
      <path
        d="M9 18.5V6.2a1 1 0 0 1 .78-.98l8-1.8a1 1 0 0 1 1.22.98v11.1M9 18.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm12-1.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
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

function songName(songs: Song[], songId: number): string {
  return songs.find((song) => song.id === songId)?.name ?? 'Unknown'
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