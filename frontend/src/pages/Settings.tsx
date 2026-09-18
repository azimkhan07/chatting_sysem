import { motion } from 'framer-motion'
import type { ReactNode } from 'react'

import { useAuthStore } from '@/stores/authStore'
import { useThemeStore } from '@/stores/themeStore'
import type { ThemeMode } from '@/lib/theme'

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
      <div className="grid h-[3.25rem] w-16 place-items-center rounded-lg bg-gradient-to-br from-[#f2f4fb] to-[#070812] py-3 ring-1 ring-black/10">
        <span className="h-1.5 w-10 rounded-full bg-white/40" />
        <span className="mt-1.5 h-3 w-10 rounded-sm bg-white/25" />
        <span className="mt-1 h-1 w-10 rounded-full bg-[#8b5cf6]" />
      </div>
    ),
  },
]

export default function Settings() {
  const user = useAuthStore((state) => state.user)
  const mode = useThemeStore((state) => state.mode)
  const setMode = useThemeStore((state) => state.setMode)

  const initials = (user?.display_name ?? '?').charAt(0).toUpperCase()
  const joined = user?.created_at
    ? new Date(user.created_at).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
    : '—'

  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <h1 className="text-2xl font-extrabold tracking-tight text-white">Settings</h1>
        <p className="text-sm text-slate-400">
          Make amteCHAT feel like yours.
        </p>
      </header>

      <motion.section
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3, ease: 'easeOut' }}
        className="glass-card p-4 sm:p-5"
      >
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-sm font-bold text-slate-200">Appearance</h2>
            <p className="mt-1 text-xs text-slate-500">
              Your choice is saved on this device — the web and mobile app each
              remember their own look.
            </p>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
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
      </motion.section>

      <motion.section
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3, delay: 0.05, ease: 'easeOut' }}
        className="glass-card p-4 sm:p-5"
      >
        <h2 className="text-sm font-bold text-slate-200">Your account</h2>
        <div className="mt-3 flex items-center gap-3">
          <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-base font-bold text-[#fff]">
            {initials}
          </span>
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-slate-100">
              {user?.display_name ?? '…'}
            </p>
            <p className="truncate text-xs text-slate-500">@{user?.username ?? '…'}</p>
          </div>
        </div>

        <dl className="mt-4 space-y-2.5 border-t border-white/5 pt-4">
          <Row label="Display name" value={user?.display_name ?? '—'} />
          <Row label="Username" value={user ? `@${user.username}` : '—'} />
          <Row label="Member since" value={joined} />
        </dl>
      </motion.section>

      <motion.p
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.3, delay: 0.1 }}
        className="px-1 text-xs text-slate-500"
      >
        amteCHAT · a social hub in the making
      </motion.p>
    </div>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <dt className="text-sm text-slate-500">{label}</dt>
      <dd className="truncate text-sm font-medium text-slate-100">{value}</dd>
    </div>
  )
}