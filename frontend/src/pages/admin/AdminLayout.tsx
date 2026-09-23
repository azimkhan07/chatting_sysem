import { NavLink, Navigate, Outlet } from 'react-router-dom'

import { BrandMark } from '@/components/AuthLayout'
import { initialsOf } from '@/lib/admin'
import { useAdminStore } from '@/stores/adminStore'

const NAV_ITEMS = [
  { to: '/admin', label: 'Dashboard', end: true },
  { to: '/admin/reviews', label: 'Reviews', end: false },
]

export default function AdminLayout() {
  const token = useAdminStore((state) => state.token)
  const admin = useAdminStore((state) => state.admin)
  const logout = useAdminStore((state) => state.logout)

  if (!token) return <Navigate to="/admin/login" replace />

  return (
    <div className="flex min-h-svh flex-col bg-midnight-950 text-slate-200">
      <header className="sticky top-0 z-20 border-b border-white/10 bg-midnight-950/90 backdrop-blur">
        <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
          <BrandMark compact />

          <nav className="flex items-center gap-1 rounded-full border border-white/10 bg-white/5 p-1">
            {NAV_ITEMS.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${
                    isActive
                      ? 'bg-brand-500 text-white'
                      : 'text-slate-400 hover:text-white'
                  }`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>

          <div className="flex items-center gap-3">
            <div className="hidden items-center gap-2.5 sm:flex">
              <span className="grid h-8 w-8 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-white">
                {admin?.avatar_url ? (
                  <img
                    src={admin.avatar_url}
                    alt=""
                    className="h-full w-full object-cover"
                  />
                ) : (
                  initialsOf(admin?.display_name)
                )}
              </span>
              <div className="text-sm leading-tight">
                <p className="font-semibold text-white">
                  {admin?.display_name ?? 'Admin'}
                </p>
                <p className="text-xs text-slate-500">@{admin?.username}</p>
              </div>
            </div>

            <button
              type="button"
              onClick={() => void logout()}
              className="rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm font-semibold text-slate-300 transition-colors hover:border-rose-500/40 hover:text-rose-300"
            >
              Sign out
            </button>
          </div>
        </div>
      </header>

      <main className="flex-1 px-4 py-8 sm:px-6">
        <div className="mx-auto w-full max-w-5xl">
          <Outlet />
        </div>
      </main>
    </div>
  )
}