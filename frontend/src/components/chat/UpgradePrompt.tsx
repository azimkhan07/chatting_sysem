import { useNavigate } from 'react-router-dom'

import { CrownIcon, XIcon } from '@/components/icons'
import { useChatEntitlements } from '@/hooks/useChatEntitlements'
import { path } from '@/lib/paths'
import type { ChatFeatureKey } from '@/types/chat'

/**
 * Shown when a locked control is tapped. Names the exact feature that is
 * locked — a generic "subscribe" would not tell the user what they just lost.
 */
export function UpgradePrompt({
  feature,
  onClose,
}: {
  feature: ChatFeatureKey
  onClose: () => void
}) {
  const navigate = useNavigate()
  const { feature: described } = useChatEntitlements()
  const meta = described(feature)

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Premium feature"
      onClick={onClose}
    >
      <div
        className="relative w-full max-w-sm rounded-2xl border border-white/10 bg-midnight-900 p-5 text-center"
        onClick={(event) => event.stopPropagation()}
      >
        <button
          type="button"
          onClick={onClose}
          aria-label="Close"
          className="absolute right-3 top-3 rounded-lg p-1.5 text-slate-500 transition hover:bg-white/5 hover:text-white"
        >
          <XIcon className="h-5 w-5" />
        </button>
        <div className="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-amber-400/15 text-amber-300">
          <CrownIcon className="h-6 w-6" />
        </div>
        <p className="mt-3 text-base font-bold text-white">
          {meta?.label ?? 'Premium chat'}
        </p>
        <p className="mt-1.5 text-sm text-slate-400">
          {meta?.blurb ?? 'This chat feature needs an active subscription.'}
        </p>
        <div className="mt-5 flex gap-2">
          <button
            type="button"
            onClick={onClose}
            className="flex-1 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/5"
          >
            Not now
          </button>
          <button
            type="button"
            onClick={() => {
              onClose()
              navigate(path('verified'))
            }}
            className="btn-primary flex-1 px-4 py-2.5"
          >
            See plans
          </button>
        </div>
      </div>
    </div>
  )
}
