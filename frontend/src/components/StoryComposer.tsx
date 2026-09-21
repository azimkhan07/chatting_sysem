import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import type { CSSProperties } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { songsApi, storiesApi, usersApi } from '@/lib/api'
import { filterCss, STORY_FILTERS } from '@/lib/storyFilters'
import type { SearchSong, Song } from '@/types/song'
import type { GifResult, TextStyle } from '@/types/story'
import type { User } from '@/types/user'

export const FONT_SIZES = ['sm', 'md', 'lg', 'xl', '2xl'] as const
export const TEXT_COLORS = ['white', 'yellow', 'red', 'green', 'blue', 'pink', 'orange', 'purple', 'black'] as const
export const TEXT_ALIGNS = ['left', 'center', 'right'] as const
export const TEXT_BGS = ['none', 'solid', 'gradient'] as const
export const TEXT_POS = ['top', 'middle', 'bottom'] as const

const COLOR_HEX: Record<string, string> = {
  white: '#ffffff',
  black: '#000000',
  yellow: '#fde047',
  red: '#f87171',
  green: '#4ade80',
  blue: '#60a5fa',
  pink: '#f472b6',
  orange: '#fb923c',
  purple: '#c084fc',
}

const FONT_PX: Record<string, number> = { sm: 13, md: 16, lg: 19, xl: 25, '2xl': 34 }

interface ResolvedTextStyle {
  color: string
  fontSize: number
  fontWeight: number
  textAlign: CSSProperties['textAlign']
  background: string
  padding: string
  borderRadius: string
  textShadow: string
}

export function resolveTextStyle(style: TextStyle | null | undefined): ResolvedTextStyle {
  const font = style?.font ?? 'md'
  const color = style?.color ?? 'white'
  const align = style?.align ?? 'center'
  const bg = style?.bg ?? 'none'
  const colorHex = COLOR_HEX[color] ?? '#ffffff'

  if (bg === 'gradient') {
    return {
      color: '#ffffff',
      fontSize: FONT_PX[font] ?? 16,
      fontWeight: 800,
      textAlign: align,
      background: 'linear-gradient(90deg, rgba(244,63,94,0.62), rgba(168,85,247,0.62))',
      padding: '6px 14px',
      borderRadius: '9999px',
      textShadow: '0 1px 4px rgba(0,0,0,0.55)',
    }
  }
  if (bg === 'solid') {
    return {
      color: colorHex,
      fontSize: FONT_PX[font] ?? 16,
      fontWeight: 800,
      textAlign: align,
      background: 'rgba(0,0,0,0.55)',
      padding: '6px 14px',
      borderRadius: '9999px',
      textShadow: '0 1px 3px rgba(0,0,0,0.6)',
    }
  }
  return {
    color: colorHex,
    fontSize: FONT_PX[font] ?? 16,
    fontWeight: 800,
    textAlign: align,
    background: 'transparent',
    padding: '2px 6px',
    borderRadius: '0px',
    textShadow: '0 1px 4px rgba(0,0,0,0.85)',
  }
}

export function textPosition(pos: TextStyle['pos']): { top?: string; bottom?: string } {
  if (pos === 'top') return { top: '5%' }
  if (pos === 'middle') return { top: '42%' }
  return { top: 'auto' }
}

interface StoryComposerProps {
  onClose: () => void
  onCreated: () => void
}

export default function StoryComposer({ onClose, onCreated }: StoryComposerProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [gif, setGif] = useState<GifResult | null>(null)
  const [caption, setCaption] = useState('')
  const [effects, setEffects] = useState('none')
  const [textStyle, setTextStyle] = useState<TextStyle>({ font: 'md', color: 'white', align: 'center', bg: 'none', pos: 'bottom' })
  const [pickedSong, setPickedSong] = useState<Song | null>(null)
  const [songs, setSongs] = useState<Song[]>([])
  const [genreFilter, setGenreFilter] = useState('All')
  const [musicTab, setMusicTab] = useState<'library' | 'search'>('library')
  const [realQuery, setRealQuery] = useState('')
  const [realResults, setRealResults] = useState<SearchSong[]>([])
  const [searchingReal, setSearchingReal] = useState(false)
  const [panel, setPanel] = useState<'music' | 'emoji' | 'stickers' | 'gifs' | null>(null)
  const [addSearch, setAddSearch] = useState('')
  const [gifResults, setGifResults] = useState<GifResult[]>([])
  const [searchingGifs, setSearchingGifs] = useState(false)
  const [previewSong, setPreviewSong] = useState<SearchSong | Song | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [mentionTerm, setMentionTerm] = useState<string | null>(null)
  const [suggestions, setSuggestions] = useState<User[]>([])
  const [searchingMentions, setSearchingMentions] = useState(false)
  const mentionDebounce = useRef<number | null>(null)

  useEffect(() => {
    inputRef.current?.click()
    void songsApi
      .list()
      .then((data) => setSongs(data.songs))
      .catch(() => setSongs([]))
    return () => {
      if (mentionDebounce.current) window.clearTimeout(mentionDebounce.current)
    }
  }, [])

  useEffect(() => {
    if (panel === 'gifs') return
    setGifResults([])
  }, [panel])

  const previewUrl = file ? URL.createObjectURL(file) : gif?.url ?? null
  const isVideo = Boolean(file?.type.startsWith('video'))
  const hasMedia = file !== null || gif !== null

  useEffect(() => {
    if (realQuery.trim().length < 2 || musicTab !== 'search') return
    setSearchingReal(true)
    const handle = window.setTimeout(() => {
      void songsApi
        .search(realQuery.trim())
        .then((data) => setRealResults(data.songs.filter((song) => song.url !== null)))
        .catch(() => setRealResults([]))
        .finally(() => setSearchingReal(false))
    }, 350)
    return () => window.clearTimeout(handle)
  }, [realQuery, musicTab])

  useEffect(() => {
    if (panel !== 'gifs' || addSearch.trim().length < 1) return
    setSearchingGifs(true)
    const handle = window.setTimeout(() => {
      void songsApi
        .gifs(addSearch.trim())
        .then((data) => setGifResults(data.gifs))
        .catch(() => setGifResults([]))
        .finally(() => setSearchingGifs(false))
    }, 450)
    return () => window.clearTimeout(handle)
  }, [addSearch, panel])

  async function pickRealSong(result: SearchSong) {
    if (!result.url) return
    try {
      const imported = await songsApi.import({
        name: result.name,
        artist: result.artist,
        url: result.url,
        genre: result.genre,
      })
      setPickedSong(imported.song)
    } catch {
      setError('Could not add that song. Try another.')
    }
  }

  async function previewRealSong(result: SearchSong) {
    if (previewSong?.name === result.name && previewSong?.artist === result.artist) {
      setPreviewSong(null)
      return
    }
    if (!result.url) return
    try {
      // Persist first so preview plays through the backend stream proxy
      // instead of a raw iTunes URL that the browser may refuse.
      const imported = await songsApi.import({
        name: result.name,
        artist: result.artist,
        url: result.url,
        genre: result.genre,
      })
      setPreviewSong(imported.song)
    } catch {
      setError('Could not preview that song. Try another.')
    }
  }

  async function submit() {
    if (!hasMedia || saving) return
    setSaving(true)
    setError(null)
    try {
      const payload = {
        caption: caption.trim(),
        effects,
        songId: pickedSong?.id ?? null,
        textStyle,
      }
      if (gif) {
        await storiesApi.createFromUrl({ url: gif.url, ...payload })
      } else if (file) {
        await storiesApi.create({ media: file, ...payload })
      }
      onCreated()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not post your story.')
      setSaving(false)
    }
  }

  function appendEmoji(emoji: string) {
    setCaption((value) => `${value}${value && !value.endsWith(' ') ? ' ' : ''}${emoji} `)
  }

  function togglePanel(next: 'music' | 'emoji' | 'stickers' | 'gifs') {
    setPanel((value) => (value === next ? null : next))
    setAddSearch('')
  }

  function updateCaption(value: string) {
    setCaption(value)
    const term = value.match(/(?:^|\s)@([A-Za-z0-9_.]*)$/)?.[1] ?? null
    setMentionTerm(term)
    if (mentionDebounce.current) window.clearTimeout(mentionDebounce.current)
    if (!term) {
      setSuggestions([])
      setSearchingMentions(false)
      return
    }
    mentionDebounce.current = window.setTimeout(() => {
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

  const genres = ['All', ...Array.from(new Set(songs.map((song) => song.genre).filter(Boolean) as string[]))]
  const pickedName = pickedSong ? `${pickedSong.name} — ${pickedSong.artist}` : ''

  return (
    <div className="no-scrollbar fixed inset-0 z-50 overflow-y-auto bg-black/70 p-4 backdrop-blur-sm">
      <div className="flex min-h-full items-center justify-center">
        <motion.div
          initial={{ opacity: 0, y: 12, scale: 0.97 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          transition={{ duration: 0.22, ease: 'easeOut' }}
          className="w-full max-w-md rounded-3xl glass-card p-5"
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
            const picked = e.target.files?.[0] ?? null
            setFile(picked)
            setGif(null)
            if (picked) setEffects('none')
          }}
        />

        <div className="relative mt-4 aspect-[3/4] max-h-80 w-full overflow-hidden rounded-2xl bg-black">
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
                Pick an image or a video, or search a GIF below — it disappears after 24 hours.
              </p>
            </div>
          )}

          {pickedSong ? (
            <div className="pointer-events-none absolute right-0 bottom-0 left-0 flex items-center gap-1.5 bg-gradient-to-t from-black/80 to-transparent px-3 pt-5 pb-2">
              <MusicNoteIcon className="h-3.5 w-3.5 shrink-0 text-brand-300" />
              <span className="truncate text-[11px] font-semibold text-[#fff]">
                <Marquee text={pickedName} />
              </span>
            </div>
          ) : null}

          {previewUrl && caption.trim() ? (
            <div
              className="pointer-events-none absolute inset-x-0 z-10 flex px-4 pb-6"
              style={{ position: 'absolute', ...textPosition(textStyle.pos), justifyContent: justifyFor(textStyle.align) }}
            >
              <p style={resolveTextStyle(textStyle)} className="max-w-full break-words">
                {caption.trim()}
              </p>
            </div>
          ) : null}
        </div>

        {hasMedia && (gif !== null || (file !== null && !isVideo)) ? (
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
                  <span className={`text-[9px] ${active ? 'text-brand-300' : 'text-slate-400'}`}>{filter.name}</span>
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
            placeholder="Type something… (use @name to mention, #tag for hashtags)"
            className="input-field mt-3"
          />

          {mentionTerm ? (
            <div className="absolute inset-x-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl bg-zinc-800/95 p-1 shadow-2xl backdrop-blur">
              {searchingMentions ? (
                <div className="flex items-center justify-center gap-2 px-3 py-2 text-xs text-[rgba(255,255,255,0.65)]">
                  <Spinner className="h-3 w-3" />
                  Searching…
                </div>
              ) : suggestions.length === 0 ? (
                <p className="px-3 py-2 text-xs text-[rgba(255,255,255,0.65)]">No one found — keep typing…</p>
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
                      <span className="block truncate text-[11px] text-[rgba(255,255,255,0.65)]">@{user.username}</span>
                    </span>
                  </button>
                ))
              )}
            </div>
          ) : null}
        </div>

        <div className="no-scrollbar mt-2 flex items-center gap-2 overflow-x-auto pb-0.5">
          {FONT_SIZES.map((size) => (
            <button
              key={size}
              type="button"
              onClick={() => setTextStyle((s) => ({ ...s, font: size }))}
              className={`story-text-option ${textStyle.font === size ? 'story-text-option-on' : ''}`}
              style={{ fontSize: FONT_PX[size] }}
              aria-label={`Text size ${size}`}
            >
              A
            </button>
          ))}
          <span className="mx-1 h-5 w-px shrink-0 bg-white/10" />
          {TEXT_COLORS.map((color) => (
            <button
              key={color}
              type="button"
              onClick={() => setTextStyle((s) => ({ ...s, color }))}
              className={`story-text-option ${textStyle.color === color ? 'story-text-option-on' : ''}`}
              style={{ background: COLOR_HEX[color], color: color === 'white' ? '#000' : '#fff' }}
              aria-label={`Text color ${color}`}
            />
          ))}
          <span className="mx-1 h-5 w-px shrink-0 bg-white/10" />
          {TEXT_ALIGNS.map((align) => (
            <button
              key={align}
              type="button"
              onClick={() => setTextStyle((s) => ({ ...s, align }))}
              className={`story-text-option ${textStyle.align === align ? 'story-text-option-on' : ''}`}
              aria-label={`Align ${align}`}
            >
              {align === 'left' ? '≡L' : align === 'center' ? '≡C' : '≡R'}
            </button>
          ))}
          {TEXT_BGS.map((bg) => (
            <button
              key={bg}
              type="button"
              onClick={() => setTextStyle((s) => ({ ...s, bg }))}
              className={`story-text-option ${textStyle.bg === bg ? 'story-text-option-on' : ''}`}
              style={
                bg === 'gradient'
                  ? { background: 'linear-gradient(90deg,#f43f5e,#a855f7)', color: '#fff' }
                  : bg === 'solid'
                    ? { background: 'rgba(0,0,0,0.6)', color: '#fff' }
                    : { border: '1px dashed rgba(255,255,255,0.4)', color: '#e2e8f0' }
              }
              aria-label={`Background ${bg}`}
            >
              {bg === 'none' ? '∅' : 'Bg'}
            </button>
          ))}
          <span className="mx-1 h-5 w-px shrink-0 bg-white/10" />
          {TEXT_POS.map((pos) => (
            <button
              key={pos}
              type="button"
              onClick={() => setTextStyle((s) => ({ ...s, pos }))}
              className={`story-text-option ${textStyle.pos === pos ? 'story-text-option-on' : ''}`}
              aria-label={`Position ${pos}`}
            >
              {pos === 'top' ? 'Top' : pos === 'middle' ? 'Mid' : 'Bot'}
            </button>
          ))}
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => togglePanel('music')}
            className={`btn-quiet px-3 py-1.5 text-xs ${pickedSong ? 'ring-2 ring-brand-400' : ''}`}
            aria-expanded={panel === 'music'}
          >
            🎵 {pickedSong ? 'Song added' : 'Add music'}
          </button>
          <button
            type="button"
            onClick={() => togglePanel('emoji')}
            className="btn-quiet px-3 py-1.5 text-xs"
            aria-expanded={panel === 'emoji'}
          >
            😀 Emoji
          </button>
          <button
            type="button"
            onClick={() => togglePanel('stickers')}
            className="btn-quiet px-3 py-1.5 text-xs"
            aria-expanded={panel === 'stickers'}
          >
            ✨ Stickers
          </button>
          <button
            type="button"
            onClick={() => togglePanel('gifs')}
            className={`btn-quiet px-3 py-1.5 text-xs ${gif ? 'ring-2 ring-brand-400' : ''}`}
            aria-expanded={panel === 'gifs'}
          >
            🎞 GIF {gif ? '(picked)' : ''}
          </button>
        </div>

        {panel === 'music' ? (
          <div className="mt-2 rounded-xl bg-white/5 p-1.5">
            <div className="mb-1.5 flex gap-1.5">
              {(['library', 'search'] as const).map((tab) => (
                <button
                  key={tab}
                  type="button"
                  onClick={() => setMusicTab(tab)}
                  className={`flex-1 rounded-lg px-2 py-1 text-[11px] font-medium transition ${
                    musicTab === tab ? 'bg-brand-500/80 text-white' : 'bg-white/10 text-slate-300 hover:bg-white/20'
                  }`}
                >
                  {tab === 'library' ? 'Music library' : 'Search real songs'}
                </button>
              ))}
            </div>

            {musicTab === 'search' ? (
              <>
                <input
                  value={realQuery}
                  onChange={(e) => setRealQuery(e.target.value)}
                  placeholder="Search Bollywood / Hollywood songs…"
                  className="w-full rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1.5 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
                />
                <div className="mt-1.5 max-h-32 space-y-1 overflow-y-auto">
                  {searchingReal ? (
                    <p className="px-2 py-2 text-center text-[11px] text-slate-500">Searching…</p>
                  ) : realResults.length === 0 && realQuery.trim().length >= 2 ? (
                    <p className="px-2 py-2 text-center text-[11px] text-slate-500">No songs found.</p>
                  ) : (
                    realResults.map((result, index) => {
                      const selected = pickedSong?.name === result.name && pickedSong?.artist === result.artist
                      const previewing = previewSong?.name === result.name && previewSong?.artist === result.artist
                      return (
                        <div
                          key={`${result.name}-${index}`}
                          className={`flex items-center gap-2 rounded-lg px-2 py-1.5 transition ${
                            selected ? 'bg-brand-500/25 ring-1 ring-brand-400/60' : 'hover:bg-white/10'
                          }`}
                        >
                          <button
                            type="button"
                            onClick={() => void previewRealSong(result)}
                            className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs transition ${
                              previewing ? 'bg-brand-500/60 text-white' : 'bg-white/10 text-brand-300'
                            }`}
                            aria-label={previewing ? 'Stop preview' : 'Preview'}
                          >
                            {previewing ? '❚❚' : '▶'}
                          </button>
                          <button
                            type="button"
                            onClick={() => void pickRealSong(result)}
                            className="min-w-0 flex-1 text-left"
                          >
                            <span className="block truncate text-xs font-semibold text-slate-200">{result.name}</span>
                            <span className="block truncate text-[11px] text-slate-500">
                              {result.artist} · {result.genre}
                            </span>
                          </button>
                          {selected ? <span className="text-xs text-brand-300">✓</span> : null}
                        </div>
                      )
                    })
                  )}
                </div>
              </>
            ) : (
              <>
                <div className="mb-1.5 flex gap-1.5 overflow-x-auto border-b border-white/10 pb-1.5">
                  {genres.map((genre) => {
                    const active = genreFilter === genre
                    return (
                      <button
                        key={genre}
                        type="button"
                        onClick={() => {
                          const next = active ? 'All' : genre
                          setGenreFilter(next)
                          if (next !== 'All') {
                            setMusicTab('search')
                            setRealQuery(next === 'Bollywood' ? 'bollywood songs' : next === 'Hollywood' ? 'hollywood songs' : `${next.toLowerCase()} songs`)
                          }
                        }}
                        className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium transition ${
                          active ? 'bg-brand-500/80 text-white' : 'bg-white/10 text-slate-300 hover:bg-white/20'
                        }`}
                      >
                        {genre}
                      </button>
                    )
                  })}
                </div>
                <div className="max-h-32 space-y-1 overflow-y-auto">
                  {songs.length === 0 ? (
                    <p className="px-2 py-3 text-center text-xs text-slate-500">Music library is empty.</p>
                  ) : (
                    songs
                      .filter((song) => genreFilter === 'All' || song.genre === genreFilter)
                      .map((song) => {
                        const active = pickedSong?.id === song.id
                        return (
                          <button
                            key={song.id}
                            type="button"
                            onClick={() => setPickedSong(active ? null : song)}
                            className={`flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition ${
                              active ? 'bg-brand-500/25 ring-1 ring-brand-400/60' : 'hover:bg-white/10'
                            }`}
                          >
                            <MusicNoteIcon className="h-4 w-4 shrink-0 text-brand-300" />
                            <span className="min-w-0">
                              <span className="block truncate text-xs font-semibold text-slate-200">{song.name}</span>
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
              </>
            )}
          </div>
        ) : null}

        {panel === 'emoji' || panel === 'stickers' || panel === 'gifs' ? (
          <div className="mt-2 rounded-xl bg-white/5 p-2">
            <input
              value={addSearch}
              onChange={(e) => setAddSearch(e.target.value)}
              placeholder={panel === 'gifs' ? 'Search GIFs…' : 'Search stickers / emoji…'}
              className="w-full rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1.5 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
            />

            {panel === 'gifs' ? (
              <div className="mt-2">
                {searchingGifs ? (
                  <div className="grid place-items-center py-6">
                    <Spinner className="h-5 w-5" />
                  </div>
                ) : addSearch.trim().length === 0 ? (
                  <p className="px-2 py-3 text-center text-[11px] text-slate-500">Search to find GIFs.</p>
                ) : gifResults.length === 0 ? (
                  <p className="px-2 py-3 text-center text-[11px] text-slate-500">No GIFs found.</p>
                ) : (
                  <div className="grid max-h-48 grid-cols-3 gap-1.5 overflow-y-auto">
                    {gifResults.map((item) => (
                      <button
                        key={item.id}
                        type="button"
                        onClick={() => {
                          setGif(item)
                          setFile(null)
                          setEffects('none')
                          setPanel(null)
                        }}
                        className={`overflow-hidden rounded-lg transition hover:ring-2 hover:ring-brand-400 ${
                          gif?.id === item.id ? 'ring-2 ring-brand-400' : ''
                        }`}
                        aria-label={item.title || 'Use GIF'}
                      >
                        <img src={item.preview_url} alt="" loading="lazy" className="aspect-square w-full object-cover" />
                      </button>
                    ))}
                  </div>
                )}
              </div>
            ) : panel === 'stickers' ? (
              <div className="mt-2 grid max-h-48 grid-cols-6 gap-1.5 overflow-y-auto">
                {STICKERS.filter((sticker) => sticker.tags.some((tag) => tag.includes(addSearch.trim().toLowerCase())) || addSearch.trim() === '')
                  .map((sticker) => (
                    <button
                      key={sticker.emoji + sticker.bg}
                      type="button"
                      onClick={() => appendEmoji(sticker.emoji)}
                      className="sticker-chip"
                      style={{ background: sticker.bg, transform: sticker.rotate ? `rotate(${sticker.rotate}deg)` : undefined }}
                      aria-label={`Add sticker ${sticker.emoji}`}
                    >
                      {sticker.emoji}
                    </button>
                  ))}
              </div>
            ) : (
              <div className="mt-2 grid max-h-72 grid-cols-8 gap-1 overflow-y-auto">
                {emojiOptions()
                  .filter((item) => item.tags.some((tag) => tag.includes(addSearch.trim().toLowerCase())) || addSearch.trim() === '')
                  .map(({ emoji }) => (
                    <button
                      key={emoji}
                      type="button"
                      onClick={() => appendEmoji(emoji)}
                      className="grid h-10 w-10 place-items-center rounded-lg text-xl leading-none transition hover:bg-white/10 active:scale-90"
                      aria-label={`Add ${emoji}`}
                    >
                      {emoji}
                    </button>
                  ))}
              </div>
            )}
          </div>
        ) : null}

        <div className="mt-3 flex items-center justify-between">
          <p className="text-[11px] text-slate-500">{pickedSong ? 'Music added · ' : 'No music · '}</p>
          <button type="button" onClick={() => void submit()} disabled={!hasMedia || saving} className="btn-primary w-auto px-4 py-2">
            {saving ? 'Posting…' : 'Share'}
          </button>
        </div>

        {error ? <p className="mt-2 text-sm text-rose-300">{error}</p> : null}
      </motion.div>

      {previewSong ? <PreviewAudio song={previewSong} onEnded={() => setPreviewSong(null)} /> : null}
      </div>
    </div>
  )
}

function songUrl(song: SearchSong | Song): string | null {
  if ('stream_url' in song && song.stream_url) return song.stream_url
  return song.url ?? null
}

function PreviewAudio({ song, onEnded }: { song: SearchSong | Song; onEnded: () => void }) {
  const ref = useRef<HTMLAudioElement>(null)
  const url = songUrl(song)
  useEffect(() => {
    const audio = ref.current
    if (!audio || !url) {
      onEnded()
      return
    }
    audio.preload = 'auto'
    audio.volume = 1
    audio.muted = false
    void audio.play().catch(() => onEnded())
  }, [url, onEnded])
  return <audio ref={ref} src={url ?? undefined} preload="auto" onError={() => onEnded()} onEnded={onEnded} />
}

function justifyFor(align: TextStyle['align']): string {
  if (align === 'left') return 'flex-start'
  if (align === 'right') return 'flex-end'
  return 'center'
}

interface StickerDef {
  emoji: string
  bg: string
  rotate?: number
  tags: string[]
}

const STICKERS: StickerDef[] = [
  { emoji: '🎉', bg: 'linear-gradient(135deg,#f59e0b,#ef4444)', tags: ['party', 'celebrate', 'birthday'] },
  { emoji: '❤️', bg: 'linear-gradient(135deg,#f43f5e,#ec4899)', tags: ['love', 'heart', 'romance'] },
  { emoji: '🔥', bg: 'linear-gradient(135deg,#f97316,#ef4444)', tags: ['fire', 'hot', 'lit'] },
  { emoji: '😂', bg: 'linear-gradient(135deg,#facc15,#f59e0b)', tags: ['laugh', 'funny', 'joy'] },
  { emoji: '😍', bg: 'linear-gradient(135deg,#ec4899,#a855f7)', tags: ['heart eyes', 'love', 'crush'] },
  { emoji: '😎', bg: 'linear-gradient(135deg,#3b82f6,#6366f1)', tags: ['cool', 'style', 'sunglasses'] },
  { emoji: '🥳', bg: 'linear-gradient(135deg,#8b5cf6,#ec4899)', tags: ['party', 'celebration'] },
  { emoji: '😜', bg: 'linear-gradient(135deg,#22c55e,#84cc16)', tags: ['silly', 'fun', 'playful'] },
  { emoji: '💯', bg: 'linear-gradient(135deg,#0ea5e9,#22c55e)', tags: ['hundred', 'perfect', 'score'] },
  { emoji: '👍', bg: 'linear-gradient(135deg,#3b82f6,#06b6d4)', tags: ['thumbs up', 'like', 'good'] },
  { emoji: '👏', bg: 'linear-gradient(135deg,#eab308,#f97316)', tags: ['clap', 'applause', 'congrats'] },
  { emoji: '🙌', bg: 'linear-gradient(135deg,#a855f7,#ec4899)', tags: ['celebrate', 'cheer', 'yay'] },
  { emoji: '🎶', bg: 'linear-gradient(135deg,#ec4899,#f59e0b)', tags: ['music', 'song', 'melody'] },
  { emoji: '⭐', bg: 'linear-gradient(135deg,#facc15,#fbbf24)', tags: ['star', 'vip', 'shine'] },
  { emoji: '✨', bg: 'linear-gradient(135deg,#a78bfa,#f0abfc)', tags: ['sparkle', 'glow', 'magic'] },
  { emoji: '⚡', bg: 'linear-gradient(135deg,#facc15,#f59e0b)', tags: ['energy', 'fast', 'power'] },
  { emoji: '🚀', bg: 'linear-gradient(135deg,#6366f1,#0ea5e9)', tags: ['rocket', 'launch', 'go'] },
  { emoji: '💪', bg: 'linear-gradient(135deg,#f97316,#ef4444)', tags: ['strong', 'workout', 'gym'] },
  { emoji: '🌙', bg: 'linear-gradient(135deg,#312e81,#7c3aed)', tags: ['night', 'moon', 'sleep'] },
  { emoji: '🌈', bg: 'linear-gradient(135deg,#f43f5e,#f59e0b,#22c55e,#3b82f6)', tags: ['rainbow', 'color', 'pride'] },
  { emoji: '🕶️', bg: 'linear-gradient(135deg,#1e293b,#475569)', tags: ['sunglasses', 'cool', 'glasses'] },
  { emoji: '🤩', bg: 'linear-gradient(135deg,#f472b6,#8b5cf6)', tags: ['amazed', 'star eyes', 'wow'] },
  { emoji: '😱', bg: 'linear-gradient(135deg,#ef4444,#f59e0b)', tags: ['shock', 'surprised', 'wow'] },
  { emoji: '😴', bg: 'linear-gradient(135deg,#6366f1,#38bdf8)', tags: ['sleep', 'tired', 'zzz'] },
  { emoji: '🐱', bg: 'linear-gradient(135deg,#f97316,#facc15)', tags: ['cat', 'pet', 'animal'] },
  { emoji: '🍕', bg: 'linear-gradient(135deg,#ef4444,#f59e0b)', tags: ['pizza', 'food', 'hungry'] },
  { emoji: '🍦', bg: 'linear-gradient(135deg,#f9a8d4,#f472b6)', tags: ['icecream', 'dessert', 'sweet'] },
]

const EMOJI_TAGS: [string, string[]][] = [
  ['😀', ['smile', 'happy', 'grin']], ['😁', ['grin', 'happy']], ['😂', ['laugh', 'funny', 'lol', 'joy']],
  ['🤣', ['laugh', 'rolling', 'funny']], ['😊', ['smile', 'happy', 'blush']], ['😇', ['angel', 'innocent']],
  ['🙂', ['smile', 'ok']], ['😉', ['wink', 'flirt']], ['😍', ['love', 'heart eyes', 'crush']], ['😘', ['kiss', 'love']],
  ['😜', ['silly', 'playful']], ['🤪', ['crazy', 'silly']], ['😎', ['cool', 'sunglasses']], ['🤩', ['star eyes', 'wow']],
  ['🥳', ['party', 'celebrate']], ['😱', ['shock', 'surprised']], ['😭', ['cry', 'sad', 'tears']], ['😢', ['sad', 'cry']],
  ['😴', ['sleep', 'tired']], ['🤔', ['thinking', 'think']], ['🤨', ['sus', 'suspicious']], ['😐', ['neutral', 'meh']],
  ['😤', ['angry', 'frustrated']], ['😡', ['mad', 'angry']], ['🤯', ['mind blown', 'wow']], ['😷', ['sick', 'mask']],
  ['🥰', ['love', 'cute']], ['😋', ['yummy', 'food']], ['🤗', ['hug', 'happy']], ['🙃', ['upside down', 'silly']],
  ['😏', ['smirk', 'flirt']], ['😒', ['unimpressed', 'eyeroll']], ['🙄', ['eyeroll', 'annoyed']], ['😬', ['awkward']],
  ['😳', ['embarrassed', 'blush']], ['🥺', ['pleading', 'sad', 'cute']], ['😌', ['calm', 'relieved', 'peaceful']],
  ['❤️', ['love', 'heart', 'red']], ['🧡', ['heart', 'orange']], ['💛', ['heart', 'yellow']], ['💚', ['heart', 'green']],
  ['💙', ['heart', 'blue']], ['💜', ['heart', 'purple']], ['🖤', ['heart', 'black']], ['🤍', ['heart', 'white']],
  ['💔', ['broken heart', 'sad']], ['💖', ['sparkling heart', 'love']], ['💕', ['two hearts', 'love']], ['💞', ['love', 'hearts']],
  ['💯', ['hundred', 'perfect']], ['💪', ['strong', 'gym', 'muscle']], ['👍', ['thumbs up', 'like', 'good']],
  ['👎', ['thumbs down', 'dislike', 'bad']], ['👏', ['clap', 'applause']], ['🙌', ['cheer', 'celebrate']],
  ['🙏', ['pray', 'please', 'thanks']], ['👋', ['hello', 'bye', 'wave']], ['🤝', ['handshake', 'deal']],
  ['✌️', ['peace', 'victory']], ['🤞', ['fingers crossed', 'luck']], ['👌', ['ok', 'perfect']],
  ['👀', ['eyes', 'watching']], ['🔥', ['fire', 'hot', 'lit']], ['✨', ['sparkle', 'shine', 'magic']],
  ['⭐', ['star', 'vip']], ['🌟', ['glowing star', 'shine']], ['🌈', ['rainbow', 'color']], ['⚡', ['energy', 'fast']],
  ['💥', ['boom', 'explosion']], ['❄️', ['snow', 'cold']], ['☀️', ['sun', 'sunny']], ['🌙', ['moon', 'night']],
  ['⛈️', ['storm', 'rain']], ['🌧️', ['rain', 'weather']], ['📷', ['camera', 'photo']], ['📸', ['camera', 'photo']],
  ['🎉', ['party', 'celebrate']], ['🎊', ['confetti', 'party']], ['🎂', ['birthday', 'cake']], ['🎁', ['gift', 'present']],
  ['🎶', ['music', 'song']], ['🎵', ['music', 'note']], ['🎤', ['mic', 'sing']], ['🎧', ['headphones', 'music']],
  ['🎬', ['movie', 'film']], ['🎮', ['game', 'gaming']], ['🏆', ['trophy', 'winner']], ['🥇', ['gold', 'winner']],
  ['🚀', ['rocket', 'launch']], ['✈️', ['plane', 'travel']], ['🏖️', ['beach', 'vacation']], ['🌴', ['palm', 'tropical']],
  ['🍕', ['pizza', 'food']], ['🍔', ['burger', 'food']], ['🍦', ['icecream', 'dessert']], ['🍫', ['chocolate', 'sweet']],
  ['☕', ['coffee', 'tea']], ['🥤', ['drink', 'soda']], ['🍟', ['fries', 'food']], ['🍿', ['popcorn', 'movie']],
  ['🍀', ['luck', 'clover']], ['🌸', ['flower', 'blossom']], ['🌹', ['rose', 'flower']], ['🌻', ['sunflower', 'flower']],
  ['🌺', ['hibiscus', 'flower']], ['💐', ['bouquet', 'flowers']], ['🐶', ['dog', 'pet']], ['🐱', ['cat', 'pet']],
  ['🐼', ['panda', 'cute']], ['🦋', ['butterfly', 'pretty']], ['🐝', ['bee', 'busy']], ['🦁', ['lion', 'roar']],
  ['🐯', ['tiger', 'animal']], ['🐸', ['frog', 'animal']], ['🐻', ['bear', 'animal']], ['🦊', ['fox', 'animal']],
  ['💃', ['dance', 'party']], ['🕺', ['dance', 'party']], ['💿', ['cd', 'disc', 'music']],
  ['🥂', ['cheers', 'toast']], ['🍾', ['champagne', 'celebrate']],
]

function emojiOptions(): { emoji: string; tags: string[] }[] {
  return EMOJI_TAGS.map(([emoji, tags]) => ({ emoji, tags }))
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

function Marquee({ text }: { text: string }) {
  const [move, setMove] = useState(false)
  useEffect(() => {
    const timer = window.setTimeout(() => setMove(true), 800)
    return () => window.clearTimeout(timer)
  }, [])
  return (
    <span className="block overflow-hidden text-nowrap text-[11px] font-semibold text-[#fff]">
      <span className={`inline-block ${move ? 'marquee-anim' : ''}`} style={{ paddingRight: '2rem' }}>
        {text}
      </span>
    </span>
  )
}