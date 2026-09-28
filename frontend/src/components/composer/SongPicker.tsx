import { useEffect, useRef, useState } from 'react'

import { songsApi } from '@/lib/api'
import type { SearchSong, Song } from '@/types/song'

interface SongPickerProps {
  picked: Song | null
  onPick: (song: Song | null) => void
  onError: (message: string | null) => void
}

/**
 * Music picker, shared by the post and story composers.
 *
 * Extracted rather than copied because the two features are the same feature:
 * both pick one song from the local library or from a real search, and both
 * need the import step that turns a search result into a stored song. A copy
 * would let the story's import path and the post's drift apart, and the bug
 * would only show on one of them.
 *
 * The import-before-preview detail matters: the browser will not reliably play
 * a raw iTunes URL, and the backend's `/songs/{id}/stream` proxy is same
 * origin, so a preview only works after the song exists.
 */
export default function SongPicker({ picked, onPick, onError }: SongPickerProps) {
  const [songs, setSongs] = useState<Song[]>([])
  const [genreFilter, setGenreFilter] = useState('All')
  const [tab, setTab] = useState<'library' | 'search'>('library')
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<SearchSong[]>([])
  const [searching, setSearching] = useState(false)
  const [preview, setPreview] = useState<Song | null>(null)
  const audio = useRef<HTMLAudioElement | null>(null)

  useEffect(() => {
    void songsApi
      .list()
      .then((data) => setSongs(data.songs))
      .catch(() => setSongs([]))
  }, [])

  // One request per query, not per keystroke. The timer is cleared on every
  // change, so only the last term in a burst actually reaches the network.
  useEffect(() => {
    if (tab !== 'search' || query.trim().length < 2) {
      setResults([])
      return
    }
    setSearching(true)
    const handle = window.setTimeout(() => {
      void songsApi
        .search(query.trim())
        // A search result with no URL cannot be imported, so it is not a
        // result the user can act on.
        .then((data) => setResults(data.songs.filter((song) => song.url !== null)))
        .catch(() => setResults([]))
        .finally(() => setSearching(false))
    }, 350)
    return () => window.clearTimeout(handle)
  }, [query, tab])

  // Stop the preview when the picker unmounts, or a closed panel keeps playing.
  useEffect(() => {
    return () => {
      audio.current?.pause()
      audio.current = null
    }
  }, [])

  async function importResult(result: SearchSong, forPreview: boolean): Promise<Song | null> {
    if (!result.url) return null
    try {
      const imported = await songsApi.import({
        name: result.name,
        artist: result.artist,
        url: result.url,
        genre: result.genre,
      })
      if (forPreview) {
        setPreview(imported.song)
      } else {
        onPick(imported.song)
        setPreview(null)
        audio.current?.pause()
      }
      return imported.song
    } catch {
      onError(
        forPreview ? 'Could not preview that song. Try another.' : 'Could not add that song. Try another.',
      )
      return null
    }
  }

  async function togglePreview(result: SearchSong) {
    const alreadyPlaying = preview?.name === result.name && preview?.artist === result.artist

    if (alreadyPlaying) {
      setPreview(null)
      audio.current?.pause()
      return
    }

    // `alreadyPlaying` returned above, so whatever is in `preview` is a
    // different song: import the new one. The return value is used instead of
    // re-reading `preview`, because `setPreview` does not update the closure
    // that is still running.
    const song = await importResult(result, true)
    if (!song) return

    const current = audio.current ?? new Audio()
    audio.current = current

    const source = previewUrlOf(song)
    if (current.src !== source) current.src = source
    void current.play().catch(() => onError('Could not play that song.'))
  }

  function chooseLibrary(song: Song) {
    if (picked?.id === song.id) {
      onPick(null)
      return
    }
    onPick(song)
    audio.current?.pause()
    setPreview(null)
  }

  // A song with no genre is not a filter, so nulls are dropped here rather
  // than rendering an empty "Unknown" chip. The predicate is explicit because
  // `filter(Boolean)` does not narrow the type on its own.
  const genres = [
    'All',
    ...Array.from(new Set(songs.map((song) => song.genre).filter((g): g is string => g !== null))),
  ]

  return (
    <div>
      {picked ? (
        <div className="mb-2 flex items-center justify-between gap-2 rounded-lg bg-brand-500/20 px-2.5 py-1.5">
          <span className="truncate text-xs font-medium text-brand-200">
            {picked.name} — {picked.artist}
          </span>
          <button
            type="button"
            onClick={() => {
              onPick(null)
              setPreview(null)
              audio.current?.pause()
            }}
            className="shrink-0 text-[11px] text-slate-400 transition hover:text-rose-300"
          >
            Remove
          </button>
        </div>
      ) : null}

      <div className="mb-2 flex gap-1 rounded-lg bg-white/5 p-0.5">
        {(['library', 'search'] as const).map((item) => (
          <button
            key={item}
            type="button"
            onClick={() => setTab(item)}
            className={`flex-1 rounded-lg px-2 py-1 text-[11px] font-medium transition ${
              tab === item
                ? 'bg-brand-500/80 text-white'
                : 'bg-white/10 text-slate-300 hover:bg-white/20'
            }`}
          >
            {item === 'library' ? 'Music library' : 'Search real songs'}
          </button>
        ))}
      </div>

      {tab === 'search' ? (
        <>
          <input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search Bollywood / Hollywood songs…"
            className="w-full rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1.5 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
          />
          <div className="mt-1.5 max-h-32 space-y-1 overflow-y-auto">
            {searching ? (
              <p className="px-2 py-2 text-center text-[11px] text-slate-500">Searching…</p>
            ) : results.length === 0 && query.trim().length >= 2 ? (
              <p className="px-2 py-2 text-center text-[11px] text-slate-500">No songs found.</p>
            ) : (
              results.map((result, index) => {
                const selected = picked?.name === result.name && picked?.artist === result.artist
                const previewing = preview?.name === result.name && preview?.artist === result.artist
                return (
                  <div
                    key={`${result.name}-${index}`}
                    className={`flex items-center gap-2 rounded-lg px-2 py-1.5 transition ${
                      selected ? 'bg-brand-500/25 ring-1 ring-brand-400/60' : 'hover:bg-white/10'
                    }`}
                  >
                    <button
                      type="button"
                      onClick={() => void togglePreview(result)}
                      className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs transition ${
                        previewing ? 'bg-brand-500/60 text-white' : 'bg-white/10 text-brand-300'
                      }`}
                      aria-label={previewing ? 'Stop preview' : 'Preview'}
                    >
                      {previewing ? '❚❚' : '▶'}
                    </button>
                    <button
                      type="button"
                      onClick={() => void importResult(result, false)}
                      className="min-w-0 flex-1 text-left"
                    >
                      <span className="block truncate text-xs font-semibold text-slate-200">
                        {result.name}
                      </span>
                      <span className="block truncate text-[11px] text-slate-500">
                        {result.artist}
                        {result.genre ? ` · ${result.genre}` : null}
                      </span>
                    </button>
                    {selected ? <span className="text-xs text-brand-300">✓</span> : null}
                  </div>
                )
              })
            )}
          </div>
        </>
      ) : songs.length === 0 ? (
        <p className="px-2 py-2 text-center text-[11px] text-slate-500">No songs in your library yet.</p>
      ) : (
        <div className="max-h-40 space-y-1 overflow-y-auto">
          <div className="mb-1 flex flex-wrap gap-1">
            {genres.map((genre) => (
              <button
                key={genre}
                type="button"
                onClick={() => setGenreFilter(genre)}
                className={`rounded-full px-2 py-0.5 text-[10px] transition ${
                  genreFilter === genre
                    ? 'bg-brand-500/70 text-white'
                    : 'bg-white/10 text-slate-300 hover:bg-white/20'
                }`}
              >
                {genre}
              </button>
            ))}
          </div>
          {songs
            .filter((song) => genreFilter === 'All' || song.genre === genreFilter)
            .map((song) => {
              const selected = picked?.id === song.id
              return (
                <button
                  key={song.id}
                  type="button"
                  onClick={() => chooseLibrary(song)}
                  className={`flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition ${
                    selected ? 'bg-brand-500/25 ring-1 ring-brand-400/60' : 'hover:bg-white/10'
                  }`}
                >
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-xs font-semibold text-slate-200">{song.name}</span>
                    <span className="block truncate text-[11px] text-slate-500">{song.artist}</span>
                  </span>
                  {selected ? <span className="text-xs text-brand-300">✓</span> : null}
                </button>
              )
            })}
        </div>
      )}
    </div>
  )
}

function previewUrlOf(song: Song | null): string {
  if (!song) return ''
  // `stream_url` is the same-origin proxy. The raw `url` is a fallback for a
  // song stored before the proxy existed.
  return song.stream_url ?? song.url
}
