import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { subscriptionsApi } from '@/lib/api'
import type { SubscriptionData, SubscriptionTier } from '@/lib/api'
import { path } from '@/lib/paths'
import { useAuthStore } from '@/stores/authStore'
import { ShieldCheckIcon } from '@/components/icons'

type Step = 'plans' | 'checkout' | 'success'

export default function Verify() {
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const [plan, setPlan] = useState<string>('amtech_basic')
  const [step, setStep] = useState<Step>('plans')
  const [active, setActive] = useState<SubscriptionData | null>(null)
  const [checkoutPayload, setCheckoutPayload] = useState<{
    gateway: string
    client_token: string
    amount_paisa: number
  } | null>(null)

  const queryClient = useQueryClient()

  const tiersQuery = useQuery({
    queryKey: ['subscriptions', 'tiers'],
    queryFn: subscriptionsApi.tiers,
    enabled: user !== null,
  })

  const verifyMutation = useMutation({
    mutationFn: () => subscriptionsApi.verify(plan),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['users', 'me'] })
    },
  })

  const checkoutMutation = useMutation({
    mutationFn: () => subscriptionsApi.checkout(plan),
    onSuccess: (data) => {
      setCheckoutPayload(data)
      setStep('checkout')
    },
  })

  const payMutation = useMutation({
    mutationFn: () => {
      const id = active?.id
      if (id === undefined) {
        const sub = verifyMutation.data?.subscription
        if (sub) return subscriptionsApi.pay(sub.id, 'mock', 'mock_client_token')
        throw new Error('No subscription on file. Verify your plan first.')
      }
      return subscriptionsApi.pay(id, 'mock', checkoutPayload?.client_token ?? 'mock_client_token')
    },
    onSuccess: (data) => {
      setActive(data.subscription)
      setStep('success')
    },
  })

  function startCheckout(): void {
    verifyMutation.mutate(undefined, {
      onSuccess: (data) => {
        setActive(data.subscription)
        checkoutMutation.mutate()
      },
    })
  }

  return (
    <div className="space-y-4">
      <header className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => navigate(path('settings'))}
          className="grid h-9 w-9 place-items-center rounded-xl bg-white/5 text-slate-300 transition hover:bg-white/10"
          aria-label="Back to settings"
        >
          ←
        </button>
        <div className="min-w-0">
          <h1 className="flex items-center gap-2 text-xl font-extrabold tracking-tight text-white">
            <ShieldCheckIcon className="h-5 w-5 text-brand-400" />
            Blue Tick
          </h1>
          <p className="text-xs text-slate-500">Get verified in minutes with a mock gateway.</p>
        </div>
      </header>

      {step === 'plans' ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {tiersQuery.data?.tiers.map((tier) => (
            <PlanCard
              key={tier.key}
              tier={tier}
              selected={plan === tier.key}
              onSelect={() => setPlan(tier.key)}
            />
          ))}
        </div>
      ) : null}

      {step === 'plans' ? (
        <motion.button
          type="button"
          onClick={startCheckout}
          disabled={verifyMutation.isPending || checkoutMutation.isPending}
          whileTap={{ scale: 0.98 }}
          className="w-full rounded-2xl bg-gradient-to-r from-brand-500 to-fuchsia-500 py-3 text-sm font-bold text-white transition hover:brightness-110 disabled:opacity-50"
        >
          {verifyMutation.isPending || checkoutMutation.isPending
            ? 'Setting up…'
            : `Continue — ${tiersQuery.data?.tiers.find((t) => t.key === plan)?.price_month ?? ''}/month`}
        </motion.button>
      ) : null}

      {step === 'checkout' && checkoutPayload ? (
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          className="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4"
        >
          <div className="flex items-center justify-between">
            <span className="text-sm text-slate-400">Mock gateway checkout</span>
            <span className="rounded-full bg-brand-500/15 px-2 py-0.5 text-xs font-semibold text-brand-300">
              ₹{ (checkoutPayload.amount_paisa / 100).toFixed(2) }
            </span>
          </div>
          <div className="rounded-xl bg-black/30 p-3 text-xs text-slate-400">
            <p className="font-mono">client_token: {checkoutPayload.client_token}</p>
            <p className="mt-1 font-mono">gateway: {checkoutPayload.gateway}</p>
          </div>
          <button
            type="button"
            onClick={() => checkoutMutation.mutate()}
            disabled={payMutation.isPending}
            className="w-full rounded-2xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-400 disabled:opacity-50"
          >
            {payMutation.isPending ? 'Paying…' : 'Pay now (mock)'}
          </button>
        </motion.div>
      ) : null}
    </div>
  )
}

function PlanCard({
  tier,
  selected,
  onSelect,
}: {
  tier: SubscriptionTier
  selected: boolean
  onSelect: () => void
}) {
  return (
    <motion.button
      type="button"
      onClick={onSelect}
      whileTap={{ scale: 0.98 }}
      aria-pressed={selected}
      className={[
        'rounded-2xl border p-4 text-left transition',
        selected
          ? 'border-brand-400/70 bg-brand-500/10'
          : 'border-white/10 bg-white/[0.03] hover:bg-white/[0.05]',
      ].join(' ')}
    >
      <p className="text-sm font-bold text-slate-100">{tier.name}</p>
      <p className="mt-1 text-2xl font-extrabold text-white">
        {tier.price_month}
        <span className="text-xs font-semibold text-slate-400"> / month</span>
      </p>
      <ul className="mt-2 space-y-1">
        {tier.perks.map((perk) => (
          <li key={perk} className="flex items-start gap-1.5 text-xs text-slate-400">
            <span className="mt-0.5 text-brand-400">✓</span>
            {perk}
          </li>
        ))}
      </ul>
    </motion.button>
  )
}
