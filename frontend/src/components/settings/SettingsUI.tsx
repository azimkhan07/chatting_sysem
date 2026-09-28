import type { ReactNode } from 'react'

/**
 * Shared primitives for the settings screens.
 *
 * No domain knowledge lives here. `Panel` used to live in this file, but the
 * page now drives one section at a time from a nav rail, so each screen is a
 * `SettingCard` instead of an accordion — and the one-at-a-time rule is
 * enforced by the page, not by every section remembering to close its siblings.
 */

export function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <dt className="shrink-0 text-[13px] text-slate-500 sm:text-sm">{label}</dt>
      <dd className="truncate text-[13px] font-medium text-slate-100 sm:text-sm">{value}</dd>
    </div>
  )
}

/** Shared field styling so every settings input matches. Compact on mobile. */
export const FIELD =
  'w-full rounded-xl bg-white/5 px-3 py-2 text-[13px] text-white placeholder:text-slate-500 outline-none ring-1 ring-white/10 focus:ring-brand-400/60 sm:py-2.5 sm:text-sm'

/** The shared outline button, for secondary actions inside a card. */
export const OUTLINE =
  'w-full rounded-xl border border-white/10 px-3 py-2 text-[13px] font-semibold text-slate-200 transition hover:border-brand-400/50 hover:text-brand-200 disabled:opacity-60 sm:px-4 sm:py-2.5 sm:text-sm'

/** The shared primary button. */
export const PRIMARY =
  'w-full rounded-xl bg-brand-500 px-3 py-2 text-[13px] font-semibold text-[#fff] transition hover:bg-brand-400 disabled:opacity-60 sm:px-4 sm:py-2.5 sm:text-sm'

/**
 * A preference switch.
 *
 * A real checkbox, not a styled div: it has to be reachable by keyboard and
 * announce its state, and the whole row is the hit target.
 *
 * The track is two steps smaller on a phone than on desktop, because a 40px
 * switch next to a 13px label looks like a toy at that size.
 */
export function Toggle({
  label,
  hint,
  checked,
  onChange,
  disabled,
  busy,
}: {
  label: string
  hint?: string
  checked: boolean
  onChange: (next: boolean) => void
  disabled?: boolean
  busy?: boolean
}) {
  return (
    <label
      className={[
        'flex items-start gap-3 rounded-xl px-2.5 py-2.5 transition sm:py-3',
        disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer active:bg-white/5',
      ].join(' ')}
    >
      <span className="min-w-0 flex-1">
        <span className="block text-[13px] font-medium text-slate-100 sm:text-sm">{label}</span>
        {hint ? (
          <span className="mt-0.5 block text-[11px] leading-snug text-slate-500">{hint}</span>
        ) : null}
      </span>
      <span className="relative mt-0.5 shrink-0">
        <input
          type="checkbox"
          role="switch"
          checked={checked}
          disabled={disabled || busy}
          onChange={(event) => onChange(event.target.checked)}
          className="peer sr-only"
        />
        <span
          aria-hidden="true"
          className={[
            'block h-5 w-9 rounded-full transition sm:h-6 sm:w-10',
            checked ? 'bg-brand-500' : 'bg-white/15',
            'peer-focus-visible:ring-4 peer-focus-visible:ring-brand-500/30',
          ].join(' ')}
        />
        <span
          aria-hidden="true"
          className={[
            'pointer-events-none absolute top-0.5 left-0.5 grid h-4 w-4 place-items-center rounded-full bg-white text-[8px] font-bold text-slate-900 transition sm:h-5 sm:w-5 sm:text-[9px]',
            checked ? 'translate-x-4 sm:translate-x-5' : '',
          ].join(' ')}
        >
          {busy ? '' : checked ? '✓' : ''}
        </span>
      </span>
    </label>
  )
}

/**
 * A small heading inside a card, for splitting one long card into labelled
 * blocks without nesting a second card.
 */
export function SubHeading({ children }: { children: ReactNode }) {
  return (
    <h3 className="px-2.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase sm:text-[11px]">
      {children}
    </h3>
  )
}

/**
 * A hairline between two blocks inside one card, so a long screen can hold
 * several labelled groups without nesting a second card.
 */
export function Divider({ children }: { children: ReactNode }) {
  return (
    <div className="mt-4 border-t border-white/5 pt-4">
      {children ? <div className="mb-1">{children}</div> : null}
    </div>
  )
}
