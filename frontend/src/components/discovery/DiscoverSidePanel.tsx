import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'

import { PremiumBadge, PremiumLock } from '@/components/chat/PremiumLock'
import { ContactMatchModal } from '@/components/contact/ContactMatchModal'
import { useAuthStore } from '@/stores/authStore'
import { ApiError, usersApi } from '@/lib/api'
import type { User } from '@/types/user'
import { useChatEntitlements } from '@/hooks/useChatEntitlements'
import type { ChatFeatureKey } from '@/types/chat'

const CONTACTS_STORAGE_KEY = 'amtech:contact-sync'

interface DiscoverSidePanelProps {
  onOpenDm: (user: User) => void
  onSearch: () => void
}

function contactsEnabled(): boolean {
  return localStorage.getItem(CONTACTS_STORAGE_KEY) === 'on'
}

/**
 * Right-hand discovery rail for the chat screen: who has the most reach, so a
 * new account has somewhere to follow from before it knows anyone. The contact
 * match block stays hidden until the user turns contact sync on — nothing is
 * sent anywhere otherwise.
 */
export function DiscoverSidePanel({ onOpenDm, onSearch }: DiscoverSidePanelProps) {
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const { isUnlocked } = useChatEntitlements()
  const canRequest = isUnlocked('message_requests')

  const [contactsOn, setContactsOn] = useState(contactsEnabled)
  const [contactsOpen, setContactsOpen] = useState(false)
  const [matched, setMatched] = useState<User[] | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const top = useQuery({
    queryKey: ['users', 'top'],
    queryFn: () => usersApi.top(8),
    staleTime: 5 * 60_000,
  })

  const followMutation = useMutation({
    mutationFn: (id: number) => usersApi.follow(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['users'] })
      void queryClient.invalidateQueries({ queryKey: ['feed'] })
    },
    onError: (error) => {
      setNotice(error instanceof ApiError ? error.message : 'Could not follow right now.')
    },
  })

  const contactsMutation = useMutation({
    mutationFn: (numbers: string[]) => usersApi.matchContacts(numbers),
    onSuccess: (result) => {
      setMatched(result.users)
      setNotice(
        result.matched > 0
          ? `${result.matched} of ${result.checked} numbers are on here.`
          : `None of those ${result.checked} numbers are on here yet.`,
      )
    },
    onError: (error) => {
      setNotice(error instanceof ApiError ? error.message : 'Could not match contacts.')
    },
  })

  // Dedupe: a contact who is already a top account is not listed twice.
  const people = useMemo(() => {
    const seen = new Set<number>()
    return [...(top.data?.users ?? []), ...(matched ?? [])].filter((user) => {
      if (user.id === me?.id || seen.has(user.id)) return false
      seen.add(user.id)
      return true
    })
  }, [top.data?.users, matched, me?.id])

  function toggleContacts() {
    const next = !contactsOn
    setContactsOn(next)
    localStorage.setItem(CONTACTS_STORAGE_KEY, next ? 'on' : 'off')
    if (!next) {
      setMatched(null)
      setNotice(null)
    }
  }

  function onLocked(feature: ChatFeatureKey) {
    setNotice(`"${feature.replace(/_/g, ' ')}" is a premium feature.`)
  }

  return (
    <aside className="chat-fill no-scrollbar hidden w-72 shrink-0 flex-col overflow-y-auto border-l border-white/5 xl:flex">
      <div className="flex items-center justify-between px-4 pt-5 pb-3">
        <h2 className="text-sm font-semibold text-slate-200">Discover</h2>
        <button
          type="button"
          onClick={onSearch}
          className="rounded-lg px-2 py-1 text-xs font-semibold text-brand-200 transition hover:bg-brand-500/15"
        >
          Find people
        </button>
      </div>

      <div className="mx-3 mb-3 rounded-xl border border-white/5 bg-white/[0.03] p-3">
        <div className="flex items-center justify-between gap-2">
          <p className="text-xs font-semibold text-slate-300">Contact sync</p>
          <button
            type="button"
            onClick={toggleContacts}
            role="switch"
            aria-checked={contactsOn}
            aria-label="Contact sync"
            className={`relative h-5 w-9 shrink-0 rounded-full transition ${
              contactsOn ? 'bg-brand-500' : 'bg-white/15'
            }`}
          >
            <span
              className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${
                contactsOn ? 'left-[1.125rem]' : 'left-0.5'
              }`}
            />
          </button>
        </div>
        <p className="mt-1.5 text-[11px] leading-relaxed text-slate-500">
          {contactsOn
            ? 'Numbers are matched on our server and never saved.'
            : 'Turn on to see which of your contacts already have an account here.'}
        </p>
        {contactsOn ? (
          <button
            type="button"
            onClick={() => setContactsOpen(true)}
            className="mt-2.5 w-full rounded-lg bg-brand-500/15 px-3 py-1.5 text-xs font-semibold text-brand-200 transition hover:bg-brand-500/25"
          >
            Find my contacts
          </button>
        ) : null}
      </div>

      {notice ? (
        <p className="mx-3 mb-2 rounded-lg bg-white/[0.04] px-3 py-2 text-[11px] text-slate-400">
          {notice}
        </p>
      ) : null}

      <p className="px-4 pb-1.5 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
        Top accounts
      </p>

      {top.isLoading ? (
        <div className="space-y-1 px-3">
          {[0, 1, 2, 3].map((index) => (
            <div key={index} className="h-14 animate-pulse rounded-xl bg-white/[0.04]" />
          ))}
        </div>
      ) : null}

      {!top.isLoading && people.length === 0 ? (
        <p className="px-4 text-xs text-slate-500">
          You already follow everyone we would suggest.
        </p>
      ) : null}

      <div className="space-y-1 px-2 pb-6">
        {people.map((user) => (
          <div
            key={user.id}
            className="flex items-center gap-2.5 rounded-xl px-2 py-2 transition hover:bg-white/[0.04]"
          >
            <span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-white">
              {user.avatar_url ? (
                <img
                  src={user.avatar_url}
                  alt=""
                  className="h-full w-full object-cover"
                  loading="lazy"
                />
              ) : (
                (user.display_name ?? '?').charAt(0).toUpperCase()
              )}
            </span>
            <div className="min-w-0 flex-1">
              <p className="flex items-center gap-1 truncate text-sm font-medium text-slate-200">
                {user.display_name}
                {user.is_verified ? <PremiumBadge title="Verified account" /> : null}
              </p>
              <p className="truncate text-xs text-slate-500">
                @{user.username}
                <span className="text-slate-600"> · {user.followers_count ?? 0} followers</span>
              </p>
            </div>
            <div className="flex shrink-0 items-center gap-1">
              {canRequest ? (
                <button
                  type="button"
                  onClick={() => onOpenDm(user)}
                  title={`Message @${user.username}`}
                  className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-brand-200"
                >
                  <MessageGlyph />
                </button>
              ) : (
                <PremiumLock
                  feature="message_requests"
                  label="Message requests"
                  onLocked={onLocked}
                />
              )}
              {user.is_followed_by_me ? (
                <span className="px-1.5 text-[11px] font-medium text-slate-500">Following</span>
              ) : (
                <button
                  type="button"
                  disabled={followMutation.isPending}
                  onClick={() => followMutation.mutate(user.id)}
                  className="rounded-lg bg-brand-500/15 px-2.5 py-1 text-xs font-semibold text-brand-200 transition hover:bg-brand-500/25 disabled:opacity-50"
                >
                  Follow
                </button>
              )}
            </div>
          </div>
        ))}
      </div>

      <ContactMatchModal
        open={contactsOpen}
        onClose={() => setContactsOpen(false)}
        onMatch={(numbers) => contactsMutation.mutate(numbers)}
        busy={contactsMutation.isPending}
      />
    </aside>
  )
}

function MessageGlyph() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
      <path
        d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.9-.9L3 20.5l1.6-4.4A8.3 8.3 0 0 1 3.6 11.5 8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4Z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
