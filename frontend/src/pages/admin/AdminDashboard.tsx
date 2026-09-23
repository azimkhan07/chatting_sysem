import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { formatPaisa } from '@/lib/admin'
import { subscriptionsAdminApi } from '@/lib/api'

const STAT_CARDS: { key: string; label: string; href: string }[] = [
  { key: 'pending', label: 'Pending review', href: '/admin/reviews?status=pending' },
  { key: 'active', label: 'Active', href: '/admin/reviews?status=active' },
  { key: 'expired', label: 'Expired', href: '/admin/reviews?status=expired' },
  { key: 'refunded', label: 'Refunded', href: '/admin/reviews?status=refunded' },
  { key: 'cancelled', label: 'Cancelled', href: '/admin/reviews?status=cancelled' },
]

export default function AdminDashboard() {
  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'stats'],
    queryFn: () => subscriptionsAdminApi.stats(),
  })

  if (isPending) {
    return (
      <div className="grid place-items-center py-24">
        <Spinner className="h-8 w-8" />
      </div>
    )
  }

  if (isError || data === null) {
    return (
      <div className="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-6 py-10 text-center text-sm text-rose-300">
        Could not load business stats.
      </div>
    )
  }

  const counts = data.stats.counts

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-extrabold tracking-tight text-white">
          Dashboard
        </h1>
        <p className="mt-1 text-sm text-slate-400">
          Blue-tick subscriptions at a glance.
        </p>
      </div>

      <div className="rounded-3xl border border-white/10 bg-gradient-to-br from-brand-500/15 to-fuchsia-500/10 p-6 sm:p-8">
        <p className="text-sm font-semibold uppercase tracking-widest text-brand-300">
          Revenue collected
        </p>
        <p className="mt-2 text-4xl font-extrabold tracking-tight text-white tabular-nums">
          {formatPaisa(data.stats.revenue_paisa)}
        </p>
        <p className="mt-2 text-sm text-slate-400">
          Paid for successful orders (refunds not counted).
        </p>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {STAT_CARDS.map((card) => (
          <Link
            key={card.key}
            to={card.href}
            className="group rounded-3xl border border-white/10 bg-slate-900/60 p-5 transition-colors hover:border-brand-500/40"
          >
            <p className="text-3xl font-extrabold text-white tabular-nums">
              {counts[card.key] ?? 0}
            </p>
            <p className="mt-1 text-sm text-slate-400 group-hover:text-slate-300">
              {card.label}
            </p>
          </Link>
        ))}
      </div>

      <p className="text-sm text-slate-500">
        Need the drill-down?{' '}
        <Link
          to="/admin/reviews?status=pending"
          className="font-semibold text-brand-400 hover:underline"
        >
          Review pending requests →
        </Link>
      </p>
    </div>
  )
}