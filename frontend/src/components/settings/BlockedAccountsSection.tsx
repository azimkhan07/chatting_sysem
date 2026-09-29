import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { Spinner } from '@/components/AuthLayout'
import { SettingCard } from '@/components/settings/SettingCard'
import { moderationApi } from '@/lib/api'

/**
 * The blocked list, which is also the only place a block can be undone.
 *
 * Worth its own card rather than a toggle, because blocking is not a setting
 * you flip and forget - it is a list of people, and the one thing a blocked
 * person is guaranteed to want is a way back. "Blocked by" is deliberately
 * absent: knowing who blocked you is information the block exists to withhold.
 */
export function BlockedAccountsSection() {
  const queryClient = useQueryClient()

  const blocks = useQuery({
    queryKey: ['moderation', 'blocks'],
    queryFn: () => moderationApi.blocks(),
  })

  const unblock = useMutation({
    mutationFn: (userId: number) => moderationApi.unblock(userId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['moderation', 'blocks'] }),
  })

  const blocked = blocks.data?.blocked ?? []

  return (
    <SettingCard
      title="Blocked accounts"
      description="People you blocked cannot see your posts or profile, and cannot follow, comment or message you. They are not told that you blocked them."
    >
      {blocks.isPending ? (
        <div className="grid place-items-center py-6">
          <Spinner />
        </div>
      ) : null}

      {!blocks.isPending && blocked.length === 0 ? (
        <p className="py-2 text-[13px] text-slate-500">
          You have not blocked anyone. You can block an account from their profile.
        </p>
      ) : null}

      {blocked.length > 0 ? (
        <ul className="divide-y divide-white/5">
          {blocked.map((user) => (
            <li key={user.id} className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500/30 to-fuchsia-500/30 text-sm font-bold text-brand-200">
                {user.display_name.charAt(0).toUpperCase()}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-[13px] font-semibold text-slate-100">
                  {user.display_name}
                </span>
                <span className="block truncate text-[11px] text-slate-500">@{user.username}</span>
              </span>
              <button
                type="button"
                onClick={() => unblock.mutate(user.id)}
                disabled={unblock.isPending}
                className="rounded-full border border-white/15 px-3 py-1.5 text-[11px] font-semibold text-slate-300 transition hover:border-white/30 hover:text-white disabled:opacity-50"
              >
                {unblock.isPending && unblock.variables === user.id ? 'Unblocking…' : 'Unblock'}
              </button>
            </li>
          ))}
        </ul>
      ) : null}

      {unblock.isError ? (
        <p className="mt-2 text-[11px] text-rose-400">
          Could not unblock that account. Try again.
        </p>
      ) : null}
    </SettingCard>
  )
}
