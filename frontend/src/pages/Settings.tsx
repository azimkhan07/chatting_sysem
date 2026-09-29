import { AnimatePresence, motion } from 'framer-motion'
import { useSearchParams } from 'react-router-dom'
import type { ReactNode } from 'react'

import {
  AccountIcon,
  AppearanceIcon,
  ArrowLeftIcon,
  BellIcon,
  ChevronRightIcon,
  FamilyIcon,
  HelpIcon,
  PrivacyIcon,
  SecurityIcon,
  UsersIcon,
} from '@/components/icons'
import { AccountCenterSection } from '@/components/settings/AccountCenterSection'
import { ContactSyncSection } from '@/components/settings/ContactSyncSection'
import { FamilySection } from '@/components/settings/FamilySection'
import { HelpSection } from '@/components/settings/HelpSection'
import { NotificationsSection, PrivacyAndBlocksSection } from '@/components/settings/PreferencesSection'
import { SecuritySection } from '@/components/settings/SecuritySection'
import { SettingCard } from '@/components/settings/SettingCard'
import { useMediaQuery } from '@/hooks/useMediaQuery'
import type { ThemeMode } from '@/lib/theme'
import { useThemeStore } from '@/stores/themeStore'

/**
 * The settings catalogue.
 *
 * This array is the entire navigation. Adding a category later is one entry
 * here plus one screen component — the mobile list, the desktop rail, the URL
 * handling and the back behaviour all read from it, so nothing else changes.
 *
 * The order is the order a person meets the consequences of their choices: how
 * it looks, who they are, who they trust, who can reach them, what it
 * interrupts them about, how it stays safe, and finally who to ask.
 */
interface Category {
  id: string
  label: string
  /** Shown under the label on the desktop rail only; it earns the space there. */
  blurb: string
  icon: ReactNode
  render: () => ReactNode
}

const CATEGORIES: Category[] = [
  {
    id: 'appearance',
    label: 'Appearance',
    blurb: 'Theme',
    icon: <AppearanceIcon className="h-5 w-5" />,
    render: () => <AppearanceSection />,
  },
  {
    id: 'account',
    label: 'Account Center',
    blurb: 'Details and your data',
    icon: <AccountIcon className="h-5 w-5" />,
    render: () => <AccountCenterSection />,
  },
  {
    id: 'family',
    label: 'Family Center',
    blurb: 'Your household',
    icon: <FamilyIcon className="h-5 w-5" />,
    render: () => <FamilySection />,
  },
  {
    id: 'privacy',
    label: 'Privacy',
    blurb: 'Who can reach you',
    icon: <PrivacyIcon className="h-5 w-5" />,
    render: () => <PrivacyAndBlocksSection />,
  },
  {
    id: 'notifications',
    label: 'Notifications',
    blurb: 'What gets your attention',
    icon: <BellIcon className="h-5 w-5" />,
    render: () => <NotificationsSection />,
  },
  {
    id: 'security',
    label: 'Security',
    blurb: 'Password and devices',
    icon: <SecurityIcon className="h-5 w-5" />,
    render: () => <SecuritySection />,
  },
  {
    id: 'contacts',
    label: 'Contact sync',
    blurb: 'Find people you know',
    icon: <UsersIcon className="h-5 w-5" />,
    render: () => <ContactSyncSection />,
  },
  {
    id: 'help',
    label: 'Help & about',
    blurb: 'Support and version',
    icon: <HelpIcon className="h-5 w-5" />,
    render: () => <HelpSection />,
  },
]

const DEFAULT_CATEGORY = CATEGORIES[0].id

function findCategory(id: string | null): Category | null {
  return CATEGORIES.find((category) => category.id === id) ?? null
}

/**
 * Settings: a list, then one screen.
 *
 * Two shapes out of one catalogue, because a phone and a laptop disagree about
 * this. On a phone there is no room for a rail beside the content, so settings
 * behaves the way every phone app does: a list of categories, and tapping one
 * pushes that screen with a back arrow. On a laptop the rail is always visible
 * and the content sits beside it.
 *
 * The distinction is behaviour, not styling, so it is decided in JS from the
 * same breakpoint the CSS uses. CSS could not do this: it can hide a rail, but
 * it cannot decide that with no selection the desktop should still show
 * Appearance while the phone should show the list.
 *
 * The selection lives in the query string, so a screen is linkable, survives a
 * refresh, and the browser back button walks back out of it.
 */
export default function Settings() {
  const isDesktop = useMediaQuery('(min-width: 768px)')
  const [params, setParams] = useSearchParams()

  // Desktop always shows a screen, defaulting to the first. Mobile shows the
  // list until something is chosen.
  const chosen = findCategory(params.get('section'))
  const active = isDesktop ? (chosen ?? findCategory(DEFAULT_CATEGORY)) : chosen

  function select(id: string) {
    setParams({ section: id })
  }

  /** Back out to the list. `replace` so a direct link in does not trap the reader. */
  function back() {
    setParams({}, { replace: true })
  }

  if (!isDesktop) {
    return <MobileSettings current={chosen} onSelect={select} onBack={back} />
  }

  return (
    <div className="mx-auto w-full max-w-4xl grid-cols-[15rem_minmax(0,1fr)] gap-8 pb-10 md:grid">
      <CategoryRail active={active} onSelect={select} />
      <div className="min-w-0">
        <AnimatePresence mode="wait" initial={false}>
          <motion.div
            key={active?.id ?? DEFAULT_CATEGORY}
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -8 }}
            transition={{ duration: 0.2, ease: 'easeOut' }}
          >
            {/* Announced so a screen reader says which screen just opened. */}
            <h2 className="sr-only">{active?.label}</h2>
            {active?.render()}
          </motion.div>
        </AnimatePresence>

        <p className="mt-6 text-center text-[11px] text-slate-600">
          amteCHAT · v1 · a social hub in the making
        </p>
      </div>
    </div>
  )
}

/** The desktop rail, always visible, always beside the content. */
function CategoryRail({
  active,
  onSelect,
}: {
  active: Category | null
  onSelect: (id: string) => void
}) {
  return (
    <nav aria-label="Settings categories" className="sticky top-4 self-start">
      <ul className="space-y-1">
        {CATEGORIES.map((category) => {
          const isActive = category.id === active?.id
          return (
            <li key={category.id}>
              <button
                type="button"
                onClick={() => onSelect(category.id)}
                aria-current={isActive ? 'page' : undefined}
                className={[
                  'group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition',
                  isActive
                    ? 'bg-brand-500/15 text-brand-100 ring-1 ring-brand-400/40'
                    : 'text-slate-400 hover:bg-white/5 hover:text-slate-200',
                ].join(' ')}
              >
                <span className={isActive ? 'shrink-0 text-brand-300' : 'shrink-0 text-slate-500'}>
                  {category.icon}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-semibold">{category.label}</span>
                  <span className="mt-0.5 block truncate text-[11px] text-slate-500">
                    {category.blurb}
                  </span>
                </span>
                <ChevronRightIcon
                  className={[
                    'h-4 w-4 shrink-0 transition group-hover:opacity-100',
                    isActive ? 'text-brand-300' : 'text-slate-600 opacity-0',
                  ].join(' ')}
                />
              </button>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}

/**
 * The phone: a list, then one screen.
 *
 * Tapping pushes a screen rather than swapping in place, which is what makes
 * the back arrow mean anything — the list is the thing you go back to. The
 * slide direction is mirrored so going back looks like going forward.
 */
function MobileSettings({
  current,
  onSelect,
  onBack,
}: {
  current: Category | null
  onSelect: (id: string) => void
  onBack: () => void
}) {
  return (
    <div className="w-full pb-28">
      <AnimatePresence mode="wait" initial={false}>
        {current === null ? (
          <motion.div
            key="list"
            initial={{ opacity: 0, x: -16 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -16 }}
            transition={{ duration: 0.2, ease: 'easeOut' }}
          >
            <h1 className="text-xl font-extrabold tracking-tight text-white">Settings</h1>
            <p className="mt-0.5 text-[13px] text-slate-400">Make amteCHAT feel like yours.</p>

            <ul className="mt-4 divide-y divide-white/5 overflow-hidden rounded-2xl bg-white/[0.03] ring-1 ring-white/10">
              {CATEGORIES.map((category) => (
                <li key={category.id}>
                  <button
                    type="button"
                    onClick={() => onSelect(category.id)}
                    className="flex w-full items-center gap-3 px-3.5 py-3 text-left transition active:bg-white/10"
                  >
                    <span className="shrink-0 text-slate-400">{category.icon}</span>
                    <span className="min-w-0 flex-1 truncate text-sm font-semibold text-slate-100">
                      {category.label}
                    </span>
                    <ChevronRightIcon className="h-4 w-4 shrink-0 text-slate-600" />
                  </button>
                </li>
              ))}
            </ul>
          </motion.div>
        ) : (
          <motion.div
            key={current.id}
            initial={{ opacity: 0, x: 16 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: 16 }}
            transition={{ duration: 0.2, ease: 'easeOut' }}
          >
            <button
              type="button"
              onClick={onBack}
              aria-label="Back to settings"
              className="-ml-1 mb-2.5 flex items-center gap-1 rounded-lg py-1.5 pr-2 pl-1 text-[13px] font-semibold text-slate-400 transition active:bg-white/5"
            >
              <ArrowLeftIcon className="h-4 w-4" />
              Settings
            </button>

            <h1 className="sr-only">{current.label}</h1>
            {current.render()}

            <p className="mt-6 text-center text-[11px] text-slate-600">
              amteCHAT · v1 · a social hub in the making
            </p>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

const THEME_OPTIONS: { mode: ThemeMode; label: string; caption: string; preview: ReactNode }[] = [
  {
    mode: 'light',
    label: 'Light',
    caption: 'Bright & airy',
    preview: (
      <div className="grid w-14 place-items-center rounded-lg bg-[#f2f4fb] py-2.5 ring-1 ring-black/10">
        <span className="h-1.5 w-9 rounded-full bg-[#dfe3ec]" />
        <span className="mt-1.5 h-2.5 w-9 rounded-sm bg-white shadow-sm" />
        <span className="mt-1 h-1 w-9 rounded-full bg-[#8b5cf6]" />
      </div>
    ),
  },
  {
    mode: 'dark',
    label: 'Dark',
    caption: 'Midnight bloom',
    preview: (
      <div className="grid w-14 place-items-center rounded-lg bg-[#070812] py-2.5 ring-1 ring-white/10">
        <span className="h-1.5 w-9 rounded-full bg-[#334155]" />
        <span className="mt-1.5 h-2.5 w-9 rounded-sm bg-[#1e293b]" />
        <span className="mt-1 h-1 w-9 rounded-full bg-[#8b5cf6]" />
      </div>
    ),
  },
  {
    mode: 'system',
    label: 'Automatic',
    caption: 'Match your device',
    preview: (
      <div className="grid w-[2.875rem] place-items-center rounded-lg bg-gradient-to-br from-[#f2f4fb] to-[#070812] py-2.5 ring-1 ring-black/10">
        <span className="h-1.5 w-9 rounded-full bg-white/40" />
        <span className="mt-1.5 h-2.5 w-9 rounded-sm bg-white/25" />
        <span className="mt-1 h-1 w-9 rounded-full bg-[#8b5cf6]" />
      </div>
    ),
  },
]

function AppearanceSection() {
  const mode = useThemeStore((state) => state.mode)
  const setMode = useThemeStore((state) => state.setMode)

  return (
    <SettingCard
      title="Appearance"
      description="How amteCHAT looks on this device. Light, dark, or whatever your system is already doing."
    >
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:gap-3">
        {THEME_OPTIONS.map((option) => {
          const selected = mode === option.mode
          return (
            <motion.button
              key={option.mode}
              type="button"
              onClick={() => setMode(option.mode)}
              whileTap={{ scale: 0.97 }}
              aria-pressed={selected}
              className={[
                'group relative flex items-center gap-3 rounded-xl border p-2.5 text-left transition sm:flex-col sm:items-stretch sm:gap-2.5 sm:p-3 sm:text-center',
                selected
                  ? 'border-brand-500/70 bg-brand-500/10'
                  : 'border-white/10 bg-white/[0.03] hover:border-brand-400/40 hover:bg-white/[0.05]',
              ].join(' ')}
            >
              <span className="flex justify-center sm:mt-0.5">{option.preview}</span>
              <span className="min-w-0 flex-1">
                <span className="flex items-center justify-start gap-1.5 text-[13px] font-semibold text-slate-100 sm:justify-center sm:text-sm">
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
                <span className="mt-0.5 block text-[11px] text-slate-500 sm:mt-1 sm:text-xs">
                  {option.caption}
                </span>
              </span>
            </motion.button>
          )
        })}
      </div>
      <p className="mt-2.5 text-[11px] leading-relaxed text-slate-500 sm:mt-3">
        Your choice is saved on this device. The web and mobile app each remember their own look.
      </p>
    </SettingCard>
  )
}
