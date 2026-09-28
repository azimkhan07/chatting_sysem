import { useState } from 'react'

import { SettingCard } from '@/components/settings/SettingCard'
import { ChevronRightIcon } from '@/components/icons'
import { Row, SubHeading } from '@/components/settings/SettingsUI'

/**
 * Help, plus the escape hatches a settings page owes the user.
 *
 * Sign-out is deliberately *not* here: the shell already has one on the sidebar
 * and one in the mobile header, and a third copy in Help is just one more place
 * for a signed-in person to click by accident. This screen is for the things
 * the shell cannot offer — a human being, a version number, a way to get your
 * data out.
 */
export function HelpSection() {
  const [showAbout, setShowAbout] = useState(false)

  return (
    <SettingCard
      title="Help & about"
      description="Reach a real person, or read what this build is made of."
    >
      <ul className="divide-y divide-white/5">
        <Link
          href="mailto:support@amtechat.app?subject=amteCHAT%20support"
          label="Contact support"
          hint="Replies come from a person, usually within a day."
        />
        <Link
          href="mailto:privacy@amtechat.app?subject=Privacy%20request"
          label="Ask a privacy question"
          hint="Or download everything we hold from Account Center, with no form at all."
        />
      </ul>

      <div className="mt-4">
        <button
          type="button"
          onClick={() => setShowAbout((value) => !value)}
          aria-expanded={showAbout}
          className="flex w-full items-center gap-3 rounded-xl border border-white/10 px-3 py-2 text-[13px] sm:px-4 sm:py-2.5 sm:text-sm font-semibold text-slate-300 transition hover:border-brand-400/50 hover:text-brand-200"
        >
          <ChevronRightIcon
            className={[
              'h-4 w-4 shrink-0 text-slate-500 transition-transform',
              showAbout ? 'rotate-90' : '',
            ].join(' ')}
          />
          {showAbout ? 'Hide' : 'About'} amteCHAT
        </button>

        {showAbout ? (
          <dl className="mt-3 space-y-2 sm:space-y-2.5 rounded-xl bg-white/[0.03] p-2.5 ring-1 sm:p-3 sm:p-3.5 ring-white/10">
            <SubHeading>This build</SubHeading>
            <Row label="Version" value="1.0.0" />
            <Row label="Terms" value="v1 · community guidelines" />
            <Row label="Contact sync" value="On-device only, off by default" />
            <Row label="Contact storage" value="Never leaves your device" />
          </dl>
        ) : null}
      </div>
    </SettingCard>
  )
}

/** A navigable row: label, hint, and a chevron that promises a destination. */
function Link({
  href,
  label,
  hint,
}: {
  href: string
  label: string
  hint: string
}) {
  return (
    <li>
      <a
        href={href}
        className="flex items-center gap-3 rounded-xl px-2.5 py-3 transition hover:bg-white/5"
      >
        <span className="min-w-0 flex-1">
          <span className="block text-[13px] sm:text-sm font-medium text-slate-100">{label}</span>
          <span className="mt-0.5 block text-[11px] leading-snug text-slate-500">{hint}</span>
        </span>
        <ChevronRightIcon className="h-4 w-4 shrink-0 text-slate-600" />
      </a>
    </li>
  )
}
