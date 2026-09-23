import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import { adminStatusStyle, formatPaisa, initialsOf } from '@/lib/admin'
import { ApiError, subscriptionsAdminApi, type SubscriptionReview } from '@/lib/api'
import { timeAgo } from '@/lib/time'

const STATUS_TABS = [
  { code: '', label: 'All' },
  { code: 'pending', label: 'Pending' },
  { code: 'active', label: 'Active' },
  { code: 'expired', label: 'Expired' },
  { code: 'refunded', label: 'Refunded' },
  { code: 'cancelled', label: 'Cancelled' },
]

interface ConfirmTarget {
  id: number
  action: 'approve' | 'reject'
  name: string
}

export default function AdminReviews() {
  const [searchParams, setSearchParams] = useSearchParams()
  const status = searchParams.get('status') ?? ''
  const page = Math.max(1, Number(searchParams.get('page') || '1') || 1)
  const queryClient = useQueryClient()
  const [confirm, setConfirm] = useState<ConfirmTarget | null>(null)

  function setStatus(next: string) {
    const params = new URLSearchParams(searchParams)
    if (next) params.set('status', next)
    else params.delete('status')
    params.delete('page')
    setSearchParams(params)
  }

  function goToPage(next: number) {
    const params = new URLSearchParams(searchParams)
    params.set('page', String(next))
    setSearchParams(params)
  }

  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'reviews', status, page],
    queryFn: () => subscriptionsAdminApi.list(status || undefined, page),
    placeholderData: (previous) => previous,
  })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['admin', 'reviews'] })
    void queryClient.invalidateQueries({ queryKey: ['admin', 'stats'] })
    setConfirm(null)
  }

  const review = useMutation({
    mutationFn: (target: ConfirmTarget) =>
      target.action === 'approve'
        ? subscriptionsAdminApi.approve(target.id)
        : subscriptionsAdminApi.reject(target.id),
    onSuccess: refresh,
  })

  if (isError || (data === null && !isPending)) {
    return (
      <div className="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-6 py-10 text-center text-sm text-rose-300">
        Could not load subscriptions.
      </div>
    )
  }

  const total = data?.meta.total ?? 0
  const perPage = data?.meta.per_page ?? 20
  const hasNext = data !== undefined && page * perPage < total
  const hasPrev = page > 1

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight text-white">
            Verification reviews
          </h1>
          <p className="mt-1 text-sm text-slate-400">
            {total} subscription{total === 1 ? '' : 's'} in this view.
          </p>
        </div>
      </div>

      <div className="flex flex-wrap gap-1.5">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.code || 'all'}
            type="button"
            onClick={() => setStatus(tab.code)}
            className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${
              status === tab.code
                ? 'bg-brand-500 text-white'
                : 'border border-white/10 bg-white/5 text-slate-400 hover:text-white'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {confirm ? (
        <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
          <p className="text-sm text-slate-300">
            {confirm.action === 'approve' ? 'Approve' : 'Reject'}{' '}
            <span className="font-semibold text-white">{confirm.name}</span>
            's verification?{' '}
            {confirm.action === 'approve'
              ? 'The blue badge goes live immediately.'
              : 'The request is refunded and no badge is issued.'}
          </p>
          {review.isError ? (
            <p className="mt-3 text-sm text-rose-300">
              {review.error instanceof ApiError
                ? review.error.message
                : 'Action failed. Please retry.'}
            </p>
          ) : null}
          <div className="mt-4 flex flex-wrap gap-2">
            <button
              type="button"
              disabled={review.isPending}
              onClick={() => review.mutate(confirm)}
              className={`rounded-full px-4 py-2 text-sm font-semibold text-white transition-colors disabled:opacity-60 ${
                confirm.action === 'approve'
                  ? 'bg-emerald-600 hover:bg-emerald-500'
                  : 'bg-rose-600 hover:bg-rose-500'
              }`}
            >
              {review.isPending ? (
                <span className="inline-flex items-center gap-2">
                  <Spinner className="h-4 w-4" /> Working…
                </span>
              ) : (
                `Confirm ${confirm.action}`
              )}
            </button>
            <button
              type="button"
              disabled={review.isPending}
              onClick={() => setConfirm(null)}
              className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-300 transition-colors hover:text-white disabled:opacity-60"
            >
              Cancel
            </button>
          </div>
        </div>
      ) : null}

      {isPending ? (
        <div className="grid place-items-center rounded-3xl border border-white/10 bg-slate-900/40 py-20">
          <Spinner className="h-8 w-8" />
        </div>
      ) : data && data.subscriptions.length === 0 ? (
        <div className="rounded-3xl border border-white/10 bg-slate-900/40 px-6 py-14 text-center text-sm text-slate-400">
          No subscriptions match this view.
        </div>
      ) : (
        <div className="space-y-3">
          {data?.subscriptions.map((subscription) => (
            <ReviewCard
              key={subscription.id}
              subscription={subscription}
              onApprove={() =>
                setConfirm({
                  id: subscription.id,
                  action: 'approve',
                  name: subscription.user?.display_name ?? 'user',
                })
              }
              onReject={() =>
                setConfirm({
                  id: subscription.id,
                  action: 'reject',
                  name: subscription.user?.display_name ?? 'user',
                })
              }
            />
          ))}
        </div>
      )}

      {data ? (
        <div className="flex items-center justify-between gap-3">
          <button
            type="button"
            disabled={!hasPrev}
            onClick={() => goToPage(page - 1)}
            className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-300 transition-colors hover:text-white disabled:opacity-40"
          >
            ← Previous
          </button>
          <span className="text-sm text-slate-500">
            Page {page}
          </span>
          <button
            type="button"
            disabled={!hasNext}
            onClick={() => goToPage(page + 1)}
            className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-300 transition-colors hover:text-white disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      ) : null}
    </div>
  )
}

function ReviewCard({
  subscription,
  onApprove,
  onReject,
}: {
  subscription: SubscriptionReview
  onApprove: () => void
  onReject: () => void
}) {
  const user = subscription.user
  const name = user?.display_name ?? 'Unknown user'
  const pending = subscription.status_code === 'pending'

  return (
    <div className="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
      <div className="flex flex-wrap items-center gap-4">
        <span className="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-sm font-bold text-white">
          {user?.avatar_url ? (
            <img
              src={user.avatar_url}
              alt=""
              className="h-full w-full object-cover"
            />
          ) : (
            initialsOf(name)
          )}
        </span>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <p className="truncate font-semibold text-white">{name}</p>
            {subscription.is_verified || user?.is_verified ? (
              <span className="text-xs text-brand-400">✓ verified</span>
            ) : null}
          </div>
          <p className="truncate text-sm text-slate-400">
            @{user?.username ?? '—'} · {subscription.plan_name} ·{' '}
            {formatPaisa(subscription.amount_paisa)}
            {subscription.paid ? ' · paid' : ' · not paid'}
          </p>
          <p className="mt-0.5 text-xs text-slate-500">
            Requested {subscription.created_at ? timeAgo(subscription.created_at) : 'recently'}
            {subscription.auto_renew ? ' · auto-renew on' : ''}
          </p>
        </div>

        <span
          className={`rounded-full border px-3 py-1 text-xs font-semibold ${adminStatusStyle(subscription.status_code)}`}
        >
          {subscription.status}
        </span>

        {pending ? (
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={onApprove}
              className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-500"
            >
              Approve
            </button>
            <button
              type="button"
              onClick={onReject}
              className="rounded-full border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-300 transition-colors hover:bg-rose-500/20"
            >
              Reject
            </button>
          </div>
        ) : null}
      </div>
    </div>
  )
}