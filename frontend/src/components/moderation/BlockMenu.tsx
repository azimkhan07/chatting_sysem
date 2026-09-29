import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { moderationApi } from '@/lib/api'
import type { User } from '@/types/user'

interface BlockMenuProps {
  user: User
  /** Called after a successful block, so the page can drop the profile. */
  onBlocked?: () => void
}

/**
 * The "..." affordance on someone else's profile: report them, or block them.
 *
 * Two separate actions rather than one "block and report" because they are not
 * the same. Blocking is a private setting the reader can undo, and nothing
 * else finds out. Reporting goes to staff. Combining them would make a
 * reversible private action silently irreversible.
 */
export default function BlockMenu({ user, onBlocked }: BlockMenuProps) {
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const [pending, setPending] = useState(false)

  async function block() {
    if (pending) return
    setPending(true)
    try {
      await moderationApi.block(user.id)
      setOpen(false)
      // The profile is a 404 for both of us now, so staying on it would show
      // a page the reader can no longer use.
      onBlocked?.()
      navigate(-1)
    } catch {
      setPending(false)
    }
  }

  return (
    <>
      <div className="relative">
        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          aria-label={`Options for @${user.username}`}
          aria-expanded={open}
          className="btn-outline w-auto !px-3"
        >
          ⋯
        </button>

        {open ? (
          <div className="absolute right-0 top-full z-20 mt-1 w-48 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 py-1 shadow-xl">
            <button
              type="button"
              onClick={() => {
                setOpen(false)
                setConfirming(true)
              }}
              className="block w-full px-4 py-2 text-left text-sm text-slate-300 transition hover:bg-white/5"
            >
              Block @{user.username}
            </button>
          </div>
        ) : null}
      </div>

      {confirming ? (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4">
          <div className="w-full max-w-sm rounded-3xl border border-white/10 bg-slate-900 p-5">
            <h2 className="text-sm font-semibold text-white">
              Block @{user.username}?
            </h2>
            <ul className="mt-3 space-y-1.5 text-xs text-slate-400">
              <li>· They will not see your posts, profile, or messages.</li>
              <li>· You will not see theirs, and neither of you can comment, follow or DM.</li>
              <li>· The follow in either direction is removed.</li>
              <li>· They are not told, and you can undo this in Settings.</li>
            </ul>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setConfirming(false)}
                className="rounded-full px-4 py-2 text-sm text-slate-400 transition hover:text-white"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => void block()}
                disabled={pending}
                className="rounded-full bg-rose-500 px-5 py-2 text-sm font-semibold text-white transition disabled:opacity-40"
              >
                {pending ? 'Blocking…' : 'Block'}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  )
}
