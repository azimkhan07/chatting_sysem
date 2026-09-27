import { useEffect, useState } from 'react'

import { CrownIcon, XIcon } from '@/components/icons'
import { WALLPAPERS } from '@/lib/wallpapers'
import type { Conversation } from '@/types/chat'

/**
 * Nickname and wallpaper are both per-viewer, per-conversation settings, so
 * they share one sheet: the point of the screen is "how this chat looks to
 * me", and splitting it in two would double the taps for no gain.
 *
 * A nickname is private: the other person never sees it. Only the viewer's own
 * copy is stored, which is why this never needs a second person's consent.
 */
export function ChatPersonalizeModal({
  conversation,
  wallpapers,
  busy,
  onClose,
  onSave,
}: {
  conversation: Conversation
  wallpapers: string[]
  busy: boolean
  onClose: () => void
  onSave: (values: { nickname: string | null; wallpaper_key: string | null }) => void
}) {
  const [nickname, setNickname] = useState(conversation.my_nickname ?? '')
  const [wallpaper, setWallpaper] = useState<string | null>(
    conversation.my_wallpaper_key ?? null,
  )

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  // Server-provided keys are authoritative; anything the client does not know
  // how to render is dropped rather than left as an invisible option.
  const options = wallpapers.length > 0 ? wallpapers : Object.keys(WALLPAPERS)
  const changed =
    (nickname.trim() || null) !== (conversation.my_nickname ?? null) ||
    wallpaper !== (conversation.my_wallpaper_key ?? null)

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Chat settings"
      onClick={onClose}
    >
      <div
        className="relative w-full max-w-sm rounded-2xl border border-white/10 bg-midnight-900 p-5"
        onClick={(event) => event.stopPropagation()}
      >
        <button
          type="button"
          onClick={onClose}
          aria-label="Close"
          className="absolute right-3 top-3 rounded-lg p-1.5 text-slate-500 transition hover:bg-white/5 hover:text-white"
        >
          <XIcon className="h-4 w-4" />
        </button>

        <p className="flex items-center gap-1.5 text-sm font-bold text-white">
          <CrownIcon className="h-4 w-4 text-amber-300" />
          {conversation.display_name}
        </p>

        <label className="mt-4 block">
          <span className="text-xs font-semibold text-slate-400">Nickname</span>
          <input
            autoFocus
            value={nickname}
            onChange={(event) => setNickname(event.target.value.slice(0, 40))}
            placeholder={conversation.display_name}
            className="mt-1.5 w-full rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-brand-400/50"
          />
          <span className="mt-1 block text-[11px] text-slate-500">
            Only you see this. {nickname.trim().length}/40
          </span>
        </label>

        <div className="mt-4">
          <p className="text-xs font-semibold text-slate-400">Wallpaper</p>
          <div className="mt-2 grid grid-cols-4 gap-2">
            {options.map((key) => {
              const meta = WALLPAPERS[key]
              const selected = wallpaper === key
              return (
                <button
                  key={key}
                  type="button"
                  onClick={() => setWallpaper(selected ? null : key)}
                  title={meta?.label ?? key}
                  aria-label={meta?.label ?? key}
                  aria-pressed={selected}
                  className={[
                    'h-12 rounded-lg border transition',
                    ['bg-midnight-950', meta?.className ?? ''].filter(Boolean).join(' '),
                    selected ? 'border-brand-400 ring-1 ring-brand-400/60' : 'border-white/10',
                  ].join(' ')}
                />
              )
            })}
          </div>
          <p className="mt-2 text-[11px] text-slate-500">
            {wallpaper === null ? 'Using the default background.' : 'Tap again to clear.'}
          </p>
        </div>

        <div className="mt-5 flex gap-2">
          <button
            type="button"
            onClick={onClose}
            className="flex-1 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/5"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={!changed || busy}
            onClick={() =>
              onSave({
                nickname: nickname.trim() || null,
                wallpaper_key: wallpaper,
              })
            }
            className="btn-primary flex-1 px-4 py-2.5"
          >
            {busy ? 'Saving…' : 'Save'}
          </button>
        </div>
      </div>
    </div>
  )
}
