import { useQuery } from '@tanstack/react-query'

import { usersApi } from '@/lib/api'
import type { MentionSuggestion } from '@/types/user'

interface MentionPickerProps {
  /** The text after the `@`. Empty for a bare `@`. */
  term: string
  onPick: (user: MentionSuggestion) => void
}

/**
 * The account list under the caption while a mention is being typed.
 *
 * Kept in the server's order and never re-sorted here. The ranking is the
 * feature: the people you follow first, then high-reach accounts. Sorting this
 * alphabetically in the client because it looked tidier would throw away the
 * only reason the endpoint exists.
 *
 * The list is instead *divided* at the boundary the server already marked, so
 * the ranking stays visible as "Following" and "Suggested" rather than one
 * undifferentiated column of strangers.
 */
export default function MentionPicker({ term, onPick }: MentionPickerProps) {
  const { data, isFetching } = useQuery({
    queryKey: ['users', 'mention-suggestions', term],
    queryFn: () => usersApi.mentionSuggestions(term),
    // A bare "@" opens on the followed accounts, so there is nothing to wait
    // for; a longer term can afford a short pause to avoid a request per key.
    staleTime: 30_000,
  })

  const users = data?.users ?? []
  const following = users.filter((user) => user.is_following)
  const suggested = users.filter((user) => !user.is_following)

  if (!isFetching && users.length === 0) {
    return (
      <div className="rounded-2xl border border-white/5 bg-slate-900/95 px-3 py-4 text-center text-xs text-slate-500">
        {term ? `No account matches @${term}` : 'No accounts to tag yet — follow someone first'}
      </div>
    )
  }

  return (
    <div className="max-h-56 overflow-y-auto rounded-2xl border border-white/5 bg-slate-900/95 p-1">
      {following.length > 0 ? <Group label="Following" users={following} onPick={onPick} /> : null}
      {suggested.length > 0 ? <Group label="Suggested" users={suggested} onPick={onPick} /> : null}
      {isFetching && users.length === 0 ? (
        <p className="px-3 py-4 text-center text-xs text-slate-500">Loading…</p>
      ) : null}
    </div>
  )
}

function Group({
  label,
  users,
  onPick,
}: {
  label: string
  users: MentionSuggestion[]
  onPick: (user: MentionSuggestion) => void
}) {
  return (
    <div>
      <p className="px-3 pt-2 pb-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
        {label}
      </p>
      {users.map((user) => (
        <button
          key={user.id}
          type="button"
          onClick={() => onPick(user)}
          className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-white/5"
        >
          {user.avatar_url ? (
            <img
              src={user.avatar_url}
              alt=""
              className="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-white/10"
            />
          ) : (
            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-[#fff]">
              {(user.display_name || user.username).charAt(0).toUpperCase()}
            </span>
          )}
          <span className="min-w-0">
            <span className="flex items-center gap-1.5">
              <span className="truncate text-sm font-semibold text-slate-100">
                {user.display_name || user.username}
              </span>
              {user.is_verified ? (
                <span className="badge bg-sky-500/15 text-[10px] text-sky-300">✓</span>
              ) : null}
            </span>
            <span className="block truncate text-xs text-slate-500">@{user.username}</span>
          </span>
        </button>
      ))}
    </div>
  )
}
