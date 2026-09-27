import { useState } from 'react'

import { Panel } from '@/components/settings/SettingsUI'

/**
 * Help, plus the escape hatches a settings page owes the user.
 *
 * Sign-out is here rather than buried in a corner of the shell: it is the one
 * action from this page that a shared device needs, and it is the one a person
 * should never have to hunt for.
 */
export function HelpSection({ open, onToggle }: { open: boolean; onToggle: () => void }) {
  const [showAbout, setShowAbout] = useState(false)

  return (
    <Panel title="Help & about" caption="Support, terms and sign-out" open={open} onToggle={onToggle}>
      <div className="space-y-2">
        <a
          href="mailto:support@amtechat.app?subject=amteCHAT%20support"
          className="block rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-brand-400/50 hover:text-brand-200"
        >
          Contact support
        </a>
        <a
          href="mailto:privacy@amtechat.app?subject=Privacy%20request"
          className="block rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-brand-400/50 hover:text-brand-200"
        >
          Request my data or ask a privacy question
        </a>
      </div>

      <p className="mt-3 text-[11px] leading-relaxed text-slate-500">
        Privacy requests are answered by a person. You can also download everything we hold from
        the Account Center above — no form required.
      </p>

      <button
        type="button"
        onClick={() => setShowAbout((value) => !value)}
        aria-expanded={showAbout}
        className="mt-4 w-full rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-slate-100"
      >
        {showAbout ? 'Hide' : 'About'} amteCHAT
      </button>

      {showAbout ? (
        <dl className="mt-3 space-y-2.5 rounded-xl bg-white/[0.03] p-3.5 text-sm ring-1 ring-white/10">
          <div className="flex items-center justify-between gap-4">
            <dt className="text-slate-500">Version</dt>
            <dd className="font-medium text-slate-100">1.0.0</dd>
          </div>
          <div className="flex items-center justify-between gap-4">
            <dt className="text-slate-500">Terms</dt>
            <dd className="font-medium text-slate-100">v1 · community guidelines</dd>
          </div>
          <div className="flex items-center justify-between gap-4">
            <dt className="text-slate-500">Contact sync</dt>
            <dd className="font-medium text-slate-100">On-device only, off by default</dd>
          </div>
        </dl>
      ) : null}
    </Panel>
  )
}
