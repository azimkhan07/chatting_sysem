import { useMutation } from '@tanstack/react-query'
import { useEffect, useState } from 'react'

import { ContactMatchModal } from '@/components/contact/ContactMatchModal'
import { SettingCard } from '@/components/settings/SettingCard'
import { OUTLINE, Toggle } from '@/components/settings/SettingsUI'
import { ApiError, usersApi } from '@/lib/api'
import { getContactSync, setContactSync, watchContactSync } from '@/lib/contactSync'

/**
 * Contact sync, explained properly.
 *
 * This screen exists mostly to say what does *not* happen, because the honest
 * version of this feature is unusual: the browser cannot read a phone book
 * without a picker we do not ship, so the list is pasted or uploaded, matched
 * against registered accounts, and discarded. A vague "sync your contacts" here
 * would promise more than the product does.
 *
 * The opt-in is shared with the chat Discover rail through `lib/contactSync`.
 */
export function ContactSyncSection() {
  const [enabled, setEnabled] = useState(getContactSync)
  const [open, setOpen] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)

  useEffect(() => watchContactSync(setEnabled), [])

  const match = useMutation({
    mutationFn: (numbers: string[]) => usersApi.matchContacts(numbers),
    onSuccess: (result) => {
      // Close the dialog: the answer is a list of people, and a list is not
      // readable through a modal that covers it.
      setOpen(false)
      setNotice(
        result.matched > 0
          ? `${result.matched} of ${result.checked} numbers have an account here.`
          : `None of those ${result.checked} numbers have an account here yet.`,
      )
    },
    onError: (error) =>
      setNotice(error instanceof ApiError ? error.message : 'Could not check those numbers.'),
  })

  function toggle(next: boolean) {
    setContactSync(next)
    setEnabled(next)
    if (!next) {
      setNotice(null)
      match.reset()
    }
  }

  const found = match.isSuccess ? match.data.users : []

  return (
    <SettingCard
      title="Contact sync"
      description="Find out which of your contacts are already on amteCHAT."
    >
      <div className="divide-y divide-white/5">
        <Toggle
          label="Show my contacts on Explore"
          hint="Off by default. Nothing about your phone book is stored on the server, ever."
          checked={enabled}
          onChange={toggle}
        />
      </div>

      {enabled ? (
        <>
          <button type="button" onClick={() => setOpen(true)} className={`mt-4 ${OUTLINE}`}>
            Find my contacts
          </button>

          {notice ? (
            <p className="mt-3 rounded-xl bg-white/[0.03] px-3 py-2 text-[11px] text-slate-300 sm:px-3.5 sm:py-2.5 sm:text-xs">
              {notice}
            </p>
          ) : null}

          {found.length > 0 ? (
            <ul className="mt-3 divide-y divide-white/5">
              {found.map((user) => (
                <li key={user.id} className="flex items-center gap-3 py-2.5">
                  <span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-white">
                    {user.avatar_url ? (
                      <img
                        src={user.avatar_url}
                        alt=""
                        loading="lazy"
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      (user.display_name ?? '?').charAt(0).toUpperCase()
                    )}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-[13px] sm:text-sm font-medium text-slate-100">
                      {user.display_name}
                    </span>
                    <span className="block truncate text-[11px] text-slate-500">
                      @{user.username}
                    </span>
                  </span>
                </li>
              ))}
            </ul>
          ) : null}
        </>
      ) : (
        <p className="mt-4 text-[11px] leading-relaxed text-slate-500">
          Turn this on if you would rather start with people you already know than an empty
          following list.
        </p>
      )}

      <p className="mt-4 text-[11px] leading-relaxed text-slate-500">
        Your browser will not hand over your address book, so paste or upload a list instead. We
        check the numbers against registered accounts, show you who turned up, and store none of
        it. Turn the switch off to remove the button from Explore.
      </p>

      <ContactMatchModal
        open={open}
        onClose={() => setOpen(false)}
        onMatch={(numbers) => match.mutate(numbers)}
        busy={match.isPending}
      />
    </SettingCard>
  )
}
