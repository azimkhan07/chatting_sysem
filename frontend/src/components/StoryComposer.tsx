import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import type { CSSProperties } from 'react'

import { Spinner } from '@/components/AuthLayout'
import MentionPicker from '@/components/composer/MentionPicker'
import SongPicker from '@/components/composer/SongPicker'
import { findActiveMention } from '@/hooks/useMentionTrigger'
import { songsApi, storiesApi } from '@/lib/api'
import { filterCss, STORY_FILTERS } from '@/lib/storyFilters'
import type { Song } from '@/types/song'
import type { GifResult, TextStyle } from '@/types/story'
import type { MentionSuggestion } from '@/types/user'

/** Matches the server's `location` column, so the UI cannot offer a longer one. */
const MAX_STORY_LOCATION_LENGTH = 255

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
  const captionInputRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [gif, setGif] = useState<GifResult | null>(null)
  const [caption, setCaption] = useState('')
  const [effects, setEffects] = useState('none')
  const [textStyle, setTextStyle] = useState<TextStyle>({ font: 'md', color: 'white', align: 'center', bg: 'none', pos: 'bottom' })
  const [pickedSong, setPickedSong] = useState<Song | null>(null)
  const [panel, setPanel] = useState<
    'music' | 'emoji' | 'stickers' | 'gifs' | 'location' | null
  >(null)
  const [addSearch, setAddSearch] = useState('')
  const [gifResults, setGifResults] = useState<GifResult[]>([])
  const [searchingGifs, setSearchingGifs] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [mentionTerm, setMentionTerm] = useState<string | null>(null)
  const [location, setLocation] = useState('')
  // The caption input is a single line, so the caret is tracked explicitly.
  // The list is driven by the fragment at the caret, not at the end of the
  // string, and splicing a pick has to leave the caret after the handle.
  const [caret, setCaret] = useState(0)

  useEffect(() => {
    inputRef.current?.click()
  }, [])

  useEffect(() => {
    if (panel === 'gifs') return
    setGifResults([])
  }, [panel])

  const previewUrl = file ? URL.createObjectURL(file) : gif?.url ?? null
  const isVideo = Boolean(file?.type.startsWith('video'))
  const hasMedia = file !== null || gif !== null

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
        // Null rather than "" so an untouched location is stored as absent
        // instead of a blank place line on the story.
        location: location.trim() === '' ? null : location.trim(),
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

  function togglePanel(next: 'music' | 'emoji' | 'stickers' | 'gifs' | 'location') {
    setPanel((value) => (value === next ? null : next))
    setAddSearch('')
  }

  function trackCaret(event: React.SyntheticEvent<HTMLInputElement>) {
    setCaret(event.currentTarget.selectionStart ?? event.currentTarget.value.length)
  }

  function updateCaption(value: string, nextCaret: number) {
    setCaption(value)
    setCaret(nextCaret)
    // Shares the composer's mention detection with the post composer, so both
    // open the picker on the same rule: a bare "@" is a mention, "@" inside an
    // email address is not.
    const active = findActiveMention(value, nextCaret)
    setMentionTerm(active?.term ?? null)
  }

  function pickMention(user: MentionSuggestion) {
    const active = findActiveMention(caption, caret)
    if (!active) {
      const next = `${caption}@${user.username} `
      setCaption(next)
      setCaret(next.length)
    } else {
      const before = caption.slice(0, active.start)
      const after = caption.slice(active.end)
      const inserted = `@${user.username} `
      const next = `${before}${inserted}${after}`
      setCaption(next)
      setCaret(before.length + inserted.length)
      // React has not re-rendered the input yet, so the DOM selection is put
      // back on the next frame. Without this the caret lands at the end of the
      // caption and the next word is typed after the mention instead of after
      // wherever the user had been typing.
      window.requestAnimationFrame(() => {
        captionInputRef.current?.setSelectionRange(next.length, next.length)
      })
    }
    setMentionTerm(null)
  }

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
            ref={captionInputRef}
            value={caption}
            onChange={(event) =>
              updateCaption(event.target.value, event.target.selectionStart ?? event.target.value.length)
            }
            onKeyUp={trackCaret}
            onClick={trackCaret}
            onSelect={trackCaret}
            maxLength={500}
            placeholder="Type something… (use @name to mention, #tag for hashtags)"
            className="input-field mt-3"
          />

          {mentionTerm !== null ? (
            <div className="absolute inset-x-0 top-full z-20 mt-1">
              <MentionPicker term={mentionTerm} onPick={pickMention} />
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
            onClick={() => togglePanel('location')}
            className={`btn-quiet px-3 py-1.5 text-xs ${location.trim() ? 'ring-2 ring-brand-400' : ''}`}
            aria-expanded={panel === 'location'}
          >
            📍 {location.trim() ? 'Location added' : 'Add location'}
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
            {/* Shared with the post composer so the two can't drift apart. */}
            <SongPicker picked={pickedSong} onPick={setPickedSong} onError={setError} />
          </div>
        ) : null}

        {panel === 'location' ? (
          <div className="mt-2 rounded-xl bg-white/5 p-1.5">
            <input
              value={location}
              onChange={(event) => setLocation(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault()
                  setPanel(null)
                }
              }}
              maxLength={MAX_STORY_LOCATION_LENGTH}
              placeholder="Add a place"
              aria-label="Story location"
              className="w-full rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1.5 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
            />
            <p className="mt-1 px-0.5 text-[11px] text-slate-500">
              A place name, not your exact position. {location.length}/{MAX_STORY_LOCATION_LENGTH}
            </p>
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

      {pickedSong ? <StoryPreviewAudio song={pickedSong} /> : null}
      </div>
    </div>
  )
}

function songUrl(song: Song): string | null {
  if (song.stream_url) return song.stream_url
  return song.url ?? null
}

/**
 * Plays the picked song under the story preview.
 *
 * Distinct from `SongPicker`'s own preview button: this is the sound the story
 * will actually publish, auditioned on the real canvas. It is deliberately
 * mounted on `pickedSong` and not on a separate "previewing" value, so what
 * plays here is exactly what the picker has chosen.
 *
 * A failed play is swallowed. The browser blocks autoplay until a user gesture
 * in some contexts, and failing to start a soundtrack must not clear the
 * song the author already picked.
 */
function StoryPreviewAudio({ song }: { song: Song }) {
  const ref = useRef<HTMLAudioElement>(null)
  const url = songUrl(song)

  useEffect(() => {
    const audio = ref.current
    if (!audio || !url) return

    audio.preload = 'auto'
    audio.volume = 1
    audio.muted = false
    void audio.play().catch(() => undefined)
  }, [url])

  return <audio ref={ref} src={url ?? undefined} preload="auto" />
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
