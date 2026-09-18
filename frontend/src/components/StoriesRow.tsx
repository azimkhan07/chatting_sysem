import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { storiesApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { Story, StoryGroup } from '@/types/story'
import type { User } from '@/types/user'

export default function StoriesRow() {
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const [creating, setCreating] = useState(false)
  const [viewingUserId, setViewingUserId] = useState<number | string | null>(null)

  const storiesQuery = useQuery({
    queryKey: ['stories'],
    queryFn: storiesApi.list,
    refetchInterval: 60_000,
  })

  const groups = storiesQuery.data?.stories ?? []
  const mine = me ? groups.find((group) => group.user.username === me.username) : undefined

  if (storiesQuery.isPending) {
    return (
      <div className="grid place-items-center py-6">
        <Spinner className="h-5 w-5" />
      </div>
    )
  }

  return (
    <>
      <div className="no-scrollbar flex items-center gap-4 overflow-x-auto pb-1">
        <button
          type="button"
          onClick={() => setCreating(true)}
          className="flex shrink-0 flex-col items-center gap-1.5"
          title="Add a story"
        >
          <span className="relative grid h-16 w-16 place-items-center rounded-full border border-white/10 bg-white/5">
            {mine && me ? <StoryRingStory user={me} /> : <CreateRing />}
          </span>
          <span className="text-xs text-slate-400">{mine ? 'Your story' : 'Add story'}</span>
        </button>

        {groups.map((group) => (
          <button
            key={group.user.id}
            type="button"
            onClick={() => setViewingUserId(group.user.id)}
            className="flex shrink-0 flex-col items-center gap-1.5"
          >
            <span className="rounded-full bg-gradient-to-tr from-amber-400 via-fuchsia-500 to-brand-400 p-[2.5px]">
              <StoryAvatar user={group.user} />
            </span>
            <span className="max-w-16 truncate text-xs text-slate-400">
              {group.user.display_name || group.user.username}
            </span>
          </button>
        ))}
      </div>

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
          onClose={() => setViewingUserId(null)}
        />
      ) : null}
    </>
  )
}

function StoryAvatar({ user }: { user: User }) {
  const initials = (user.display_name || user.username).charAt(0).toUpperCase()
  return (
    <span className="grid h-[60px] w-[60px] place-items-center rounded-full bg-midnight-950 text-xl font-bold text-white ring-2 ring-midnight-950">
      {initials}
    </span>
  )
}

function StoryRingStory({ user }: { user: User }) {
  const initials = (user.display_name || user.username).charAt(0).toUpperCase()
  return (
    <span className="z-10 grid h-[60px] w-[60px] place-items-center rounded-full bg-midnight-950 text-xl font-bold text-white ring-2 ring-midnight-950">
      {initials}
    </span>
  )
}

function CreateRing() {
  return (
    <span className="grid h-[58px] w-[58px] place-items-center rounded-full text-2xl text-brand-300">
      +
    </span>
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
      <div className="w-full max-w-sm rounded-3xl border border-white/10 bg-midnight-900 p-5">
        <div className="flex items-center justify-between">
          <h2 className="text-base font-bold text-white">New story</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white"
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

        {previewUrl ? (
          file?.type.startsWith('video') ? (
            <video src={previewUrl} autoPlay muted loop playsInline className="mt-4 max-h-64 w-full rounded-2xl bg-black" />
          ) : (
            <img src={previewUrl} alt="" className="mt-4 max-h-64 w-full rounded-2xl bg-black object-contain" />
          )
        ) : (
          <p className="mt-4 rounded-2xl border border-dashed border-white/10 bg-white/[0.02] p-8 text-center text-sm text-slate-400">
            Pick an image or a video (max 100 MB)
          </p>
        )}

        <input
          value={caption}
          onChange={(e) => setCaption(e.target.value)}
          maxLength={500}
          placeholder="Add a caption…"
          className="input mt-3"
        />

        {error ? <p className="mt-2 text-sm text-rose-300">{error}</p> : null}

        <button
          type="button"
          onClick={() => void submit()}
          disabled={!file || saving}
          className="btn-primary mt-4 w-full"
        >
          {saving ? 'Posting…' : 'Share'}
        </button>
      </div>
    </div>
  )
}

interface StoryViewerProps {
  groups: StoryGroup[]
  initialUserId: number | string
  onClose: () => void
}

interface FlatStory {
  owner: User
  story: Story
}

export function StoryViewer({ groups, initialUserId, onClose }: StoryViewerProps) {
  const items = groups.flatMap<FlatStory>((group) =>
    group.stories.map((story) => ({ owner: group.user, story })),
  )
  const startIndex = Math.max(
    0,
    items.findIndex((item) => item.owner.id === initialUserId),
  )
  const [index, setIndex] = useState(startIndex)
  const [paused, setPaused] = useState(false)
  const current = items[index]

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowRight') setIndex((i) => Math.min(items.length - 1, i + 1))
      if (e.key === 'ArrowLeft') setIndex((i) => Math.max(0, i - 1))
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose, items.length])

  useEffect(() => {
    if (!current || current.story.type !== 'image' || paused) return
    const timer = window.setTimeout(
      () => setIndex((i) => (i < items.length - 1 ? i + 1 : i)),
      5000,
    )
    return () => window.clearTimeout(timer)
  }, [current, paused, items.length])

  if (!current) return null

  return (
    <div className="fixed inset-0 z-50 bg-black">
      <div className="relative mx-auto h-full w-full sm:max-w-[430px]">
        <div className="absolute inset-x-0 top-0 z-20 p-3">
          <div className="flex gap-1.5">
            {items.map((item, i) => (
              <span
                key={item.story.id}
                className={`h-0.5 flex-1 rounded-full transition ${
                  i < index
                    ? 'bg-white'
                    : i === index
                      ? 'bg-white'
                      : 'bg-white/30'
                }`}
              />
            ))}
          </div>
          <div className="mt-3 flex items-center gap-3">
            <span className="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-white">
              {(current.owner.display_name || current.owner.username).charAt(0).toUpperCase()}
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-white">
                {current.owner.display_name || current.owner.username}
              </p>
              <p className="text-xs text-white/60">
                {relativeTime(current.story.created_at)}
              </p>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="ml-auto rounded-lg p-1.5 text-white/80 hover:bg-white/10"
              aria-label="Close"
            >
              ✕
            </button>
          </div>
        </div>

        <div className="flex h-full items-center justify-center">
          {current.story.type === 'video' ? (
            <VideoStory
              story={current.story}
              onEnded={() => setIndex((i) => Math.min(items.length - 1, i + 1))}
              onPauseChange={setPaused}
            />
          ) : (
            <img
              src={current.story.url}
              alt=""
              className="max-h-full w-full object-contain"
            />
          )}
        </div>

        {current.story.caption ? (
          <p className="absolute inset-x-0 bottom-16 z-20 px-6 text-center text-sm text-white">
            {current.story.caption}
          </p>
        ) : null}

        <button
          type="button"
          aria-label="Previous"
          className="absolute inset-y-0 left-0 z-10 w-1/3"
          onClick={() => setIndex((i) => Math.max(0, i - 1))}
        />
        <button
          type="button"
          aria-label="Next"
          className="absolute inset-y-0 right-0 z-10 w-2/3"
          onClick={() =>
            setIndex((i) => {
              if (i < items.length - 1) return i + 1
              onClose()
              return i
            })
          }
        />
      </div>
    </div>
  )
}

function VideoStory({
  story,
  onEnded,
  onPauseChange,
}: {
  story: Story
  onEnded: () => void
  onPauseChange: (paused: boolean) => void
}) {
  const ref = useRef<HTMLVideoElement>(null)

  useEffect(() => {
    const video = ref.current
    if (!video) return
    video.currentTime = 0
    void video.play()
    const handlePause = () => onPauseChange(video.paused && !video.ended)
    video.addEventListener('pause', handlePause)
    video.addEventListener('play', handlePause)
    return () => {
      video.removeEventListener('pause', handlePause)
      video.removeEventListener('play', handlePause)
    }
  }, [onPauseChange, story.id])

  return (
    <video
      ref={ref}
      src={story.url}
      autoPlay
      playsInline
      onEnded={onEnded}
      className="max-h-full w-full object-contain"
    />
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