import { useQuery } from '@tanstack/react-query'
import { AnimatePresence, motion } from 'framer-motion'
import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import PostCard from '@/components/PostCard'
import { ArchiveIcon } from '@/components/icons'
import { archiveApi } from '@/lib/api'
import { path } from '@/lib/paths'
import type { ArchiveDayPosts, ArchiveDayStories } from '@/types/archive'
import type { Post } from '@/types/post'
import type { Story } from '@/types/story'

const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
]

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']

const MONTH_ORDER = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]

/** Calendar starts on Monday so the grid matches the weekday labels. */
function firstWeekdayOffset(year: number, month: number): number {
  const first = new Date(year, month, 1)
  return (first.getDay() + 6) % 7
}

function daysInMonth(year: number, month: number): number {
  return new Date(year, month + 1, 0).getDate()
}

export default function ArchivePage() {
  const navigate = useNavigate()
  const today = useMemo(() => {
    const now = new Date()
    now.setHours(0, 0, 0, 0)
    return now
  }, [])
  const todayKey = useMemo(() => toKey(today), [today])

  const [year, setYear] = useState<number>(today.getFullYear())
  const [selected, setSelected] = useState<string>(todayKey)
  const [kind, setKind] = useState<'posts' | 'stories'>('posts')
  const [openedPost, setOpenedPost] = useState<Post | null>(null)

  const allowedYears = useMemo(() => {
    const current = today.getFullYear()
    // Archive has a rolling one-year window, so the previous and current
    // year are always the two that can hold a kept day.
    return [current - 1, current]
  }, [today])

  const calendarQuery = useQuery({
    queryKey: ['archive', 'calendar', year],
    queryFn: () => archiveApi.calendar(year),
  })
  const postsQuery = useQuery<ArchiveDayPosts>({
    queryKey: ['archive', 'day', selected, 'posts'],
    queryFn: () => archiveApi.posts(selected),
    enabled: selected !== null && kind === 'posts',
  })
  const storiesQuery = useQuery<ArchiveDayStories>({
    queryKey: ['archive', 'day', selected, 'stories'],
    queryFn: () => archiveApi.stories(selected),
    enabled: selected !== null && kind === 'stories',
  })

  const days = calendarQuery.data?.days ?? {}
  const activeContent = kind === 'posts' ? postsQuery : storiesQuery
  const items = activeContent.data ?? null
  const dayPosts = kind === 'posts' ? (items as ArchiveDayPosts | null)?.posts ?? [] : []
  const dayStories = kind === 'stories' ? (items as ArchiveDayStories | null)?.stories ?? [] : []

  return (
    <div className="mx-auto w-full max-w-4xl pb-28 pt-2">
      <header className="mb-4 flex items-center gap-3">
        <button
          type="button"
          onClick={() => navigate(path('settings'))}
          className="grid h-9 w-9 place-items-center rounded-xl bg-white/5 text-slate-300 transition hover:bg-white/10"
          aria-label="Back to settings"
        >
          ←
        </button>
        <div className="min-w-0">
          <h1 className="flex items-center gap-2 text-xl font-extrabold tracking-tight text-white">
            <ArchiveIcon className="h-5 w-5 text-brand-400" />
            Archive
          </h1>
          <p className="text-xs text-slate-500">Your hidden posts and stories.</p>
        </div>
      </header>

      <div className="mb-4 flex items-center justify-between gap-2">
        <div className="flex items-center gap-1 rounded-xl bg-white/5 p-1">
          {(['posts', 'stories'] as const).map((option) => (
            <button
              key={option}
              type="button"
              onClick={() => setKind(option)}
              aria-pressed={kind === option}
              className={[
                'rounded-lg px-4 py-1.5 text-sm font-semibold transition',
                kind === option
                  ? 'bg-white/10 text-slate-200'
                  : 'text-slate-400 hover:text-slate-200',
              ].join(' ')}
            >
              {option === 'posts' ? 'Posts' : 'Stories'}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-1">
          {allowedYears.map((allowedYear) => (
            <button
              key={allowedYear}
              type="button"
              onClick={() => setYear(allowedYear)}
              className={[
                'rounded-lg px-3 py-1.5 text-sm font-semibold transition',
                year === allowedYear
                  ? 'bg-white/10 text-slate-200'
                  : 'text-slate-400 hover:text-slate-200',
              ].join(' ')}
              aria-current={year === allowedYear ? 'page' : undefined}
            >
              {allowedYear}
            </button>
          ))}
        </div>
      </div>

      {calendarQuery.isPending ? (
        <div className="grid place-items-center py-16">
          <Spinner className="h-6 w-6" />
        </div>
      ) : calendarQuery.isError ? (
        <div className="rounded-3xl border border-dashed border-white/10 px-6 py-10 text-center text-sm text-slate-400">
          Could not load your archive calendar.
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {MONTH_ORDER.map((month) => (
            <MonthCard
              key={month}
              year={year}
              month={month}
              todayKey={todayKey}
              selected={selected}
              days={days}
              onSelect={(key) => setSelected(key)}
            />
          ))}
        </div>
      )}

      <section className="mt-5 space-y-1">
        <h2 className="px-1 text-sm font-bold text-slate-300">
          {formatTitle(selected)} · {kind === 'posts' ? 'Posts' : 'Stories'}
        </h2>

        {activeContent.isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : activeContent.isError ? (
          <div className="rounded-3xl border border-dashed border-white/10 px-6 py-14 text-center text-sm text-slate-400">
            Could not load that day.
          </div>
        ) : items === null || dayPosts.length === 0 && kind === 'posts' ||
          dayStories.length === 0 && kind === 'stories' ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">Nothing that day</p>
            <p className="mt-1 text-sm text-slate-400">
              {kind === 'posts' ? 'No posts were kept' : 'No stories were kept'} from that day.
            </p>
          </div>
        ) : kind === 'posts' ? (
          <div className="grid grid-cols-3 gap-1.5">
            {dayPosts.map((post) => (
              <button
                key={post.id}
                type="button"
                onClick={() => setOpenedPost(post)}
                className="group relative aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10"
                aria-label="Open archived post"
              >
                {post.media.find((m) => m.type === 'image') ? (
                  <img
                    src={post.media.find((m) => m.type === 'image')!.url}
                    alt=""
                    loading="lazy"
                    className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                  />
                ) : post.media.find((m) => m.type === 'video') ? (
                  <video
                    src={post.media.find((m) => m.type === 'video')!.url}
                    muted
                    playsInline
                    preload="metadata"
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <div className="flex h-full w-full items-end bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 p-2">
                    <p className="line-clamp-2 text-[11px] leading-tight text-slate-300">
                      {post.body}
                    </p>
                  </div>
                )}
              </button>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-3 gap-1.5">
            {dayStories.map((story) => (
              <StoryTile key={story.id} story={story} />
            ))}
          </div>
        )}
      </section>

      <AnimatePresence>
        {openedPost ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/85 p-4 backdrop-blur-sm"
          >
            <div className="w-full max-w-md">
              <PostCard post={openedPost} cacheKey={['archive', 'day', selected]} archived />
            </div>
            <button
              type="button"
              onClick={() => setOpenedPost(null)}
              className="fixed top-4 right-4 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/10 text-lg text-[#fff] backdrop-blur transition hover:bg-white/20"
              aria-label="Close"
            >
              ✕
            </button>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  )
}

function StoryTile({ story }: { story: Story }) {
  return (
    <div className="group relative aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10">
      {story.type === 'image' ? (
        <img
          src={story.url}
          alt=""
          loading="lazy"
          className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
        />
      ) : (
        <video
          src={story.url}
          muted
          playsInline
          preload="metadata"
          className="h-full w-full object-cover"
        />
      )}
      {story.caption ? (
        <span className="absolute inset-x-0 bottom-0 hidden bg-gradient-to-t from-black/70 to-transparent p-1.5 group-hover:block">
          <p className="truncate text-[10px] text-[#fff]">{story.caption}</p>
        </span>
      ) : null}
    </div>
  )
}

function MonthCard({
  year,
  month,
  todayKey,
  selected,
  days,
  onSelect,
}: {
  year: number
  month: number
  todayKey: string
  selected: string
  days: Record<string, { posts: number; stories: number }>
  onSelect: (key: string) => void
}) {
  const offset = firstWeekdayOffset(year, month)
  const count = daysInMonth(year, month)
  const cells = Array.from({ length: offset + count }, (_, index) => {
    const dayNumber = index - offset + 1
    const date = new Date(year, month, dayNumber)
    const key = toKey(date)
    const has = key in days
    const isToday = key === todayKey
    const isSelected = key === selected
    return { dayNumber, key, has, isToday, isSelected }
  })

  return (
    <div className="rounded-2xl bg-white/[0.03] p-3 ring-1 ring-white/10">
      <h3 className="mb-2 text-sm font-bold text-slate-200">{MONTHS[month]}</h3>
      <div className="grid grid-cols-7 gap-y-0.5">
        {WEEKDAYS.map((weekday) => (
          <span
            key={weekday}
            className="pb-0.5 text-center text-[9px] font-medium text-slate-600"
          >
            {weekday}
          </span>
        ))}
        {cells.map((cell) =>
          cell.dayNumber < 1 ? (
            <span key={`empty-${cell.dayNumber}`} />
          ) : (
            <button
              key={cell.key}
              type="button"
              onClick={() => onSelect(cell.key)}
              aria-label={`${MONTHS[month]} ${cell.dayNumber}`}
              aria-pressed={cell.isSelected}
              className={[
                'relative grid h-7 place-items-center rounded-md text-[11px] font-medium transition',
                cell.isSelected
                  ? 'bg-brand-500 text-[#fff]'
                  : cell.isToday
                    ? 'text-slate-100 ring-1 ring-inset ring-white/25'
                    : 'text-slate-400 hover:bg-white/10 hover:text-slate-200',
              ].join(' ')}
            >
              {cell.dayNumber}
              {cell.has && !cell.isSelected ? (
                <span aria-hidden="true" className="absolute bottom-0.5 h-1 w-1 rounded-full bg-brand-400" />
              ) : null}
            </button>
          ),
        )}
      </div>
    </div>
  )
}

function toKey(date: Date): string {
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${mm}-${dd}`
}

/** `2026-09-29` -> `29 Sep 2026` without a locale dependency. */
function formatTitle(key: string): string {
  const [y, m, d] = key.split('-').map(Number)
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${d} ${months[(m ?? 1) - 1]} ${y}`
}