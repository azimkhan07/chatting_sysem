import { useEffect } from 'react'

import { XIcon } from '@/components/icons'
import type { ReadReceipt } from '@/types/chat'

/**
 * "Who has seen this" — the small DP stack under an outgoing message is a
 * summary, and tapping it opens this so the name and the exact seen time are
 * both readable instead of being crammed into a tooltip.
 */
export function SeenBySheet({
  receipts,
  onClose,
}: {
  receipts: ReadReceipt[]
  onClose: () => void
}) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-3 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-label="Seen by"
      onClick={onClose}
    >
      <div
        className="relative w-full max-w-xs rounded-2xl border border-white/10 bg-midnight-900 p-4"
        onClick={(event) => event.stopPropagation()}
      >
        <button
          type="button"
          onClick={onClose}
          aria-label="Close"
          className="absolute right-2.5 top-2.5 rounded-lg p-1.5 text-slate-500 transition hover:bg-white/5 hover:text-white"
        >
          <XIcon className="h-4 w-4" />
        </button>
        <p className="text-sm font-bold text-white">
          {receipts.length === 1 ? 'Seen by 1 person' : `Seen by ${receipts.length} people`}
        </p>
        <ul className="mt-3 space-y-2.5">
          {receipts.map((reader) => (
            <li key={reader.id} className="flex items-center gap-2.5">
              {reader.avatar_url ? (
                <img
                  src={reader.avatar_url}
                  alt=""
                  className="h-8 w-8 shrink-0 rounded-full object-cover"
                />
              ) : (
                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-[#fff]">
                  {(reader.display_name || reader.username).charAt(0).toUpperCase()}
                </span>
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-slate-100">
                  {reader.display_name || reader.username}
                </p>
                <p className="truncate text-[11px] text-slate-500">@{reader.username}</p>
              </div>
              <span className="shrink-0 text-[11px] text-slate-500">Seen</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}
