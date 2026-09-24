import { AnimatePresence, motion } from 'framer-motion'
import { NavLink, useLocation } from 'react-router-dom'
import type { ReactNode } from 'react'

import { BrandMark, Spinner } from '@/components/AuthLayout'
import SupportChat from '@/components/SupportChat'
import { LogoutIcon } from '@/components/icons'
import { useAuthStore } from '@/stores/authStore'

export interface NavItem {
  to: string
  label: string
  icon: ReactNode
  badge?: number
}

interface AppShellProps {
  nav: NavItem[]
  children: ReactNode
}

export default function AppShell({ nav, children }: AppShellProps) {
  const location = useLocation()
  const user = useAuthStore((state) => state.user)
  const logout = useAuthStore((state) => state.logout)
  const isChat = location.pathname === '/chat' || location.pathname.startsWith('/chat/')

  return (
    <div className="app-shell relative flex flex-col bg-midnight-950">
      <div className="mesh-bg">
        <div className="orb -top-48 left-1/4 h-96 w-96 bg-brand-600/15" />
        <div className="orb bottom-0 right-0 h-80 w-80 bg-fuchsia-600/10 [animation-delay:-9s]" />
      </div>

      <div className="relative z-10 flex min-h-0 flex-1">
        <aside className="hidden w-60 shrink-0 flex-col border-r border-white/5 p-4 md:flex">
          <div className="px-2">
            <BrandMark compact />
          </div>

          <nav className="mt-8 flex flex-col gap-1" aria-label="Primary">
            {nav.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) =>
                  [
                    'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition',
                    isActive
                      ? 'bg-brand-500/15 text-brand-200'
                      : 'text-slate-400 hover:bg-white/5 hover:text-slate-200',
                  ].join(' ')
                }
              >
                {item.icon}
                <span className="min-w-0 flex-1 truncate">{item.label}</span>
                {item.badge ? (
                  <span className="rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-[#fff]">
                    {item.badge > 99 ? '99+' : item.badge}
                  </span>
                ) : null}
              </NavLink>
            ))}
          </nav>

          <div className="mt-auto">
            <div className="flex items-center gap-2.5 rounded-xl border border-white/5 bg-white/[0.03] p-2.5">
              <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-[#fff]">
                {(user?.display_name ?? '?').charAt(0).toUpperCase()}
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-slate-200">
                  {user?.display_name ?? '…'}
                </p>
                <p className="truncate text-xs text-slate-500">
                  @{user?.username ?? '…'}
                </p>
              </div>
              <button
                type="button"
                onClick={() => void logout()}
                title="Sign out"
                className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-rose-300"
              >
                <LogoutIcon className="h-4 w-4" />
              </button>
            </div>
          </div>
        </aside>

        <main
          className={
            isChat
              ? 'flex min-w-0 flex-1 flex-col overflow-hidden pb-14 md:pb-0'
              : 'no-scrollbar min-w-0 flex-1 overflow-y-auto'
          }
        >
          {!isChat ? (
            <header className="flex h-14 items-center justify-between px-4 md:hidden">
              <BrandMark compact />
              <button
                type="button"
                onClick={() => void logout()}
                className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-rose-300"
                title="Sign out"
              >
                <LogoutIcon className="h-4 w-4" />
              </button>
            </header>
          ) : null}

          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={location.pathname}
              initial={{ opacity: 0, y: isChat ? 0 : 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: isChat ? 0 : -8 }}
              transition={{ duration: 0.25, ease: 'easeOut' }}
              className={
                isChat
                  ? 'flex min-h-0 flex-1 flex-col'
                  : 'w-full px-4 pb-28 pt-4 md:px-6 md:pb-8'
              }
            >
              {children}
            </motion.div>
          </AnimatePresence>
        </main>
      </div>

      <nav
        className="absolute inset-x-0 bottom-0 z-20 flex h-14 items-center justify-around border-t border-white/5 bg-midnight-950/80 backdrop-blur-lg md:hidden"
        aria-label="Primary mobile"
      >
        {nav.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === '/'}
            className={({ isActive }) =>
              [
                'flex flex-1 flex-col items-center gap-0.5 py-1 text-[10px] transition',
                isActive ? 'text-brand-300' : 'text-slate-500',
              ].join(' ')
            }
          >
            <span className="relative">
              {item.icon}
              {item.badge ? (
                <span className="absolute -right-2 -top-1.5 grid h-3.5 w-3.5 place-items-center rounded-full bg-brand-500 text-[8px] font-bold text-[#fff]">
                  {item.badge > 9 ? '9+' : item.badge}
                </span>
              ) : null}
            </span>
            {item.label}
          </NavLink>
        ))}
      </nav>

      <SupportChat />
    </div>
  )
}

export function ShellLoading() {
  return (
    <div className="grid h-full min-h-[40svh] place-items-center">
      <Spinner className="h-7 w-7" />
    </div>
  )
}