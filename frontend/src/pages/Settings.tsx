import { motion } from 'framer-motion'
import { useState, type ReactNode } from 'react'

import { AccountCenterSection } from '@/components/settings/AccountCenterSection'
import { FamilySection } from '@/components/settings/FamilySection'
import { HelpSection } from '@/components/settings/HelpSection'
import { Panel } from '@/components/settings/SettingsUI'
import { PreferencesSection } from '@/components/settings/PreferencesSection'
import { SecuritySection } from '@/components/settings/SecuritySection'
import type { ThemeMode } from '@/lib/theme'
import { useThemeStore } from '@/stores/themeStore'

interface ThemeOption {
  mode: ThemeMode
  label: string
  caption: string
  preview: ReactNode
}

const OPTIONS: ThemeOption[] = [
  {
    mode: 'light',
    label: 'Light',
    caption: 'Bright & airy',
    preview: (
      <div className="grid w-16 place-items-center rounded-lg bg-[#f2f4fb] py-3 ring-1 ring-black/10">
        <span className="h-1.5 w-10 rounded-full bg-[#dfe3ec]" />
        <span className="mt-1.5 h-3 w-10 rounded-sm bg-white shadow-sm" />
        <span className="mt-1 h-1 w-10 rounded-full bg-[#8b5cf6]" />
      </div>
    ),
  },
  {
    mode: 'dark',
    label: 'Dark',
    caption: 'Midnight bloom',
    preview: (
      <div className="grid w-16 place-items-center rounded-lg bg-[#070812] py-3 ring-1 ring-white/10">
        <span className="h-1.5 w-10 rounded-full bg-[#334155]" />
        <span className="mt-1.5 h-3 w-10 rounded-sm bg-[#1e293b]" />
        <span className="mt-1 h-1 w-10 rounded-full bg-[#8b5cf6]" />
      </div>
    ),
  },
  {
    mode: 'system',
    label: 'Automatic',
    caption: 'Match your device',
    preview: (
      <div className="grid w-[3.25rem] place-items-center rounded-lg bg-gradient-to-br from-[#f2f4fb] to-[#070812] py-3 ring-1 ring-black/10">
        <span className="h-1.5 w-10 rounded-full bg-white/40" />
        <span className="mt-1.5 h-3 w-10 rounded-sm bg-white/25" />
        <span className="mt-1 h-1 w-10 rounded-full bg-[#8b5cf6]" />
      </div>
    ),
  },
]

/**
 * Only one section is open at a time.
 *
 * The page grew to six groups, four of which are full screens of controls. One
 * long scroll would mean a person hunting for Appearance had to page past the
 * "Delete account" button, which is both tedious and the wrong thing to put in
 * their way.
 */
export default function Settings() {
  const mode = useThemeStore((state) => state.mode)
  const setMode = useThemeStore((state) => state.setMode)
  const [open, setOpen] = useState<string | null>('appearance')

  const toggle = (id: string) => () => setOpen((current) => (current === id ? null : id))

  return (
    <div className="mx-auto w-full max-w-2xl space-y-4 pb-24">
      <header className="space-y-1">
        <h1 className="text-2xl font-extrabold tracking-tight text-white">Settings</h1>
        <p className="text-sm text-slate-400">Make amteCHAT feel like yours.</p>
      </header>

      <Panel
        title="Appearance"
        caption="Theme"
        open={open === 'appearance'}
        onToggle={toggle('appearance')}
      >
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {OPTIONS.map((option) => {
            const selected = mode === option.mode
            return (
              <motion.button
                key={option.mode}
                type="button"
                onClick={() => setMode(option.mode)}
                whileTap={{ scale: 0.97 }}
                aria-pressed={selected}
                className={[
                  'group relative flex items-center gap-3 rounded-2xl border p-3 text-left transition sm:flex-col sm:items-stretch sm:gap-3 sm:text-center',
                  selected
                    ? 'border-brand-500/70 bg-brand-500/10'
                    : 'border-white/10 bg-white/[0.03] hover:border-brand-400/40 hover:bg-white/[0.05]',
                ].join(' ')}
              >
                <span className="flex justify-center sm:mt-1">{option.preview}</span>
                <span className="min-w-0 flex-1 sm:mt-1">
                  <span className="flex items-center justify-start gap-1.5 text-sm font-semibold text-slate-100 sm:justify-center">
                    {option.label}
                    {selected ? (
                      <motion.span
                        layoutId="theme-check"
                        initial={{ scale: 0.5 }}
                        animate={{ scale: 1 }}
                        className="grid h-4 w-4 place-items-center rounded-full bg-brand-500 text-[10px] font-bold text-[#fff]"
                      >
                        ✓
                      </motion.span>
                    ) : null}
                  </span>
                  <span className="mt-0.5 block text-xs text-slate-500 sm:mt-1">
                    {option.caption}
                  </span>
                </span>
              </motion.button>
            )
          })}
        </div>
        <p className="mt-3 text-[11px] text-slate-500">
          Your choice is saved on this device. The web and mobile app each remember their own look.
        </p>
      </Panel>

      <AccountCenterSection open={open === 'account'} onToggle={toggle('account')} />
      <PreferencesSection open={open === 'preferences'} onToggle={toggle('preferences')} />
      <SecuritySection open={open === 'security'} onToggle={toggle('security')} />
      <FamilySection open={open === 'family'} onToggle={toggle('family')} />
      <HelpSection open={open === 'help'} onToggle={toggle('help')} />

      <motion.p
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        className="px-1 pt-1 text-center text-xs text-slate-500"
      >
        amteCHAT · v1 · a social hub in the making
      </motion.p>
    </div>
  )
}
