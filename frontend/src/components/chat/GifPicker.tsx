import { useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { useQuery } from '@tanstack/react-query'

import { songsApi } from '@/lib/api'
import type { GifResult } from '@/types/story'

const SEARCH_DEBOUNCE_MS = 350
const TRENDING = ['happy', 'reaction', 'celebration', 'love', 'funny', 'yes']

export function GifPicker({
  onPick,
  onClose,
}: {
  onPick: (gif: GifResult) => void
  onClose: () => void
}) {
  const [term, setTerm] = useState('')
  const [query, setQuery] = useState('')
  const lastTyped = useRef(0)

  const gifs = useQuery({
    queryKey: ['chat', 'gifs', query],
    queryFn: () => songsApi.gifs(query),
    enabled: query.length > 0,
  })

  return (
    <div className="absolute bottom-full left-0 z-20 mb-2 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-white/10 bg-midnight-900 p-3 shadow-2xl">
      <div className="flex items-center gap-2">
        <input
          autoFocus
          value={term}
          onChange={(event) => {
            setTerm(event.target.value)
            const now = Date.now()
            if (now - lastTyped.current > SEARCH_DEBOUNCE_MS) {
              lastTyped.current = now
              setQuery(event.target.value.trim())
            }
          }}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              event.preventDefault()
              setQuery(term.trim())
            }
          }}
          onBlur={() => setQuery(term.trim())}
          placeholder="Search GIFs"
          aria-label="Search GIFs"
          className="flex-1 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
        />
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-400 transition hover:bg-white/5 hover:text-white"
        >
          Close
        </button>
      </div>

      {query.length === 0 ? (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {TRENDING.map((tag) => (
            <button
              key={tag}
              type="button"
              onClick={() => setQuery(tag)}
              className="rounded-full bg-white/5 px-2.5 py-1 text-xs font-medium text-slate-300 transition hover:bg-white/10"
            >
              {tag}
            </button>
          ))}
          <p className="mt-1 w-full text-xs text-slate-500">
            Pick a search to load GIFs.
          </p>
        </div>
      ) : null}

      {gifs.isPending ? (
        <div className="grid place-items-center py-8">
          <Spinner className="h-5 w-5" />
        </div>
      ) : null}

      {gifs.isError ? (
        <p className="py-6 text-center text-xs text-slate-500">
          GIFs are unavailable right now.
        </p>
      ) : null}

      {gifs.data && gifs.data.gifs.length === 0 ? (
        <p className="py-6 text-center text-xs text-slate-500">No GIFs found.</p>
      ) : null}

      {gifs.data && gifs.data.gifs.length > 0 ? (
        <div className="mt-3 grid max-h-64 grid-cols-3 gap-1.5 overflow-y-auto">
          {gifs.data.gifs.map((gif) => (
            <button
              key={gif.id}
              type="button"
              onClick={() => onPick(gif)}
              title={gif.title}
              className="overflow-hidden rounded-lg bg-white/5 transition hover:ring-1 hover:ring-brand-400/60"
            >
              <img src={gif.preview_url} alt={gif.title} loading="lazy" className="h-16 w-full object-cover" />
            </button>
          ))}
        </div>
      ) : null}
    </div>
  )
}
