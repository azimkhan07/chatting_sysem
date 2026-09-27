import { CrownIcon } from '@/components/icons'
import type { ChatFeatureKey } from '@/types/chat'

/**
 * The small crown that marks a premium control. Purely affordance: the backend
 * rejects a locked call with `FEATURE_LOCKED` whatever the client renders.
 */
export function PremiumLock({
  feature,
  label,
  onLocked,
}: {
  feature: ChatFeatureKey
  label: string
  onLocked: (feature: ChatFeatureKey) => void
}) {
  return (
    <button
      type="button"
      onClick={() => onLocked(feature)}
      title={`${label} · Premium`}
      aria-label={`${label} is a premium feature. Tap to see how to unlock it.`}
      className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-amber-400/15 text-amber-300 transition hover:bg-amber-400/25"
    >
      <CrownIcon className="h-3.5 w-3.5" />
    </button>
  )
}

/** Marks a conversation or row as premium without being interactive. */
export function PremiumBadge({ title }: { title: string }) {
  return (
    <span
      title={title}
      className="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-amber-400/15 text-amber-300"
    >
      <CrownIcon className="h-3 w-3" />
    </span>
  )
}
