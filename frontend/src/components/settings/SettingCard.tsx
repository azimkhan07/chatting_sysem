import type { ReactNode } from 'react'

/**
 * The right-hand pane of a settings screen: a titled card.
 *
 * The title and description live here rather than in the page, because with a
 * nav rail the page renders exactly one card at a time and cannot know which
 * one it is showing.
 *
 * Sizes step up at `sm`. A phone has to fit a heading, a hint and a control
 * inside one screen's worth of height, so the mobile values are the compact
 * ones and `sm` restores the roomier desktop scale.
 */
export function SettingCard({
  title,
  description,
  children,
  footer,
}: {
  title: string
  description?: string
  children: ReactNode
  footer?: ReactNode
}) {
  return (
    <section className="overflow-hidden rounded-2xl bg-white/[0.03] ring-1 ring-white/10">
      <header className="border-b border-white/5 px-3.5 py-2.5 sm:px-5 sm:py-3.5">
        <h2 className="text-[13px] font-bold text-slate-100 sm:text-base">{title}</h2>
        {description ? (
          <p className="mt-0.5 text-[11px] leading-relaxed text-slate-400 sm:mt-1 sm:text-[13px]">
            {description}
          </p>
        ) : null}
      </header>
      <div className="px-3.5 py-3 sm:px-5 sm:py-4">{children}</div>
      {footer ? (
        <footer className="border-t border-white/5 bg-white/[0.02] px-3.5 py-2.5 sm:px-5 sm:py-3">
          {footer}
        </footer>
      ) : null}
    </section>
  )
}
