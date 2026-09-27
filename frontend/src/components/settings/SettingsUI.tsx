import type { ReactNode } from 'react'

/**
 * The shared shell for one settings group, plus the small pieces every group
 * needs. Lives in its own module so a section component can use it without
 * importing the page that renders it.
 */
export function Panel({
  title,
  caption,
  open,
  onToggle,
  children,
  tone = 'default',
}: {
  title: string
  caption?: string
  open: boolean
  onToggle: () => void
  children: ReactNode
  tone?: 'default' | 'danger'
}) {
  const id = title.toLowerCase().replace(/[^a-z]+/g, '-').replace(/^-|-$/g, '')

  return (
    <section
      className={[
        'overflow-hidden rounded-2xl bg-white/[0.03] ring-1 transition',
        tone === 'danger' ? 'ring-rose-500/20' : 'ring-white/10',
      ].join(' ')}
    >
      <button
        id={`settings-${id}-toggle`}
        type="button"
        onClick={onToggle}
        aria-expanded={open}
        aria-controls={`settings-${id}-panel`}
        className="flex w-full items-center gap-3 px-4 py-3.5 text-left transition hover:bg-white/[0.03]"
      >
        <span className="min-w-0 flex-1">
          <span
            className={[
              'block text-sm font-semibold',
              tone === 'danger' ? 'text-rose-300' : 'text-slate-100',
            ].join(' ')}
          >
            {title}
          </span>
          {caption ? <span className="mt-0.5 block text-xs text-slate-500">{caption}</span> : null}
        </span>
        <span
          aria-hidden="true"
          className={`shrink-0 text-xs text-slate-500 transition-transform ${open ? 'rotate-90' : ''}`}
        >
          ›
        </span>
      </button>
      {open ? (
        <div
          id={`settings-${id}-panel`}
          role="region"
          aria-labelledby={`settings-${id}-toggle`}
          className="border-t border-white/5 px-4 py-4"
        >
          {children}
        </div>
      ) : null}
    </section>
  )
}

export function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <dt className="shrink-0 text-sm text-slate-500">{label}</dt>
      <dd className="truncate text-sm font-medium text-slate-100">{value}</dd>
    </div>
  )
}

/** Shared field styling so every settings input matches. */
export const FIELD =
  'w-full rounded-xl bg-white/5 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 outline-none ring-1 ring-white/10 focus:ring-brand-400/60'

/**
 * A preference switch.
 *
 * A real checkbox, not a styled div: it has to be reachable by keyboard and
 * announce its state, and the whole row is the hit target.
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
        'flex items-start gap-3 rounded-xl px-2.5 py-2.5 transition',
        disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:bg-white/5',
      ].join(' ')}
    >
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium text-slate-100">{label}</span>
        {hint ? <span className="mt-0.5 block text-[11px] leading-snug text-slate-500">{hint}</span> : null}
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
            'block h-6 w-10 rounded-full transition',
            checked ? 'bg-brand-500' : 'bg-white/15',
            'peer-focus-visible:ring-4 peer-focus-visible:ring-brand-500/30',
          ].join(' ')}
        />
        <span
          aria-hidden="true"
          className={[
            'pointer-events-none absolute top-0.5 left-0.5 grid h-5 w-5 place-items-center rounded-full bg-white text-[9px] font-bold text-slate-900 transition',
            checked ? 'translate-x-4' : '',
          ].join(' ')}
        >
          {busy ? '' : checked ? '✓' : ''}
        </span>
      </span>
    </label>
  )
}
