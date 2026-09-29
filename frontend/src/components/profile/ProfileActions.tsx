import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import FollowButton from '@/components/FollowButton'
import BlockMenu from '@/components/moderation/BlockMenu'
import { ContactSheet } from '@/components/profile/ContactSheet'
import { chatApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { User } from '@/types/user'

interface ProfileActionsProps {
  user: User
  onChanged?: () => void
}

/**
 * The visitor's two actions on a profile, always as a primary + outlined pair.
 *
 * A personal account gets Message, because there is nothing else to offer. A
 * professional or business account that has published a contact gets Contact
 * instead, so the outreach channel is the one the owner chose. Follow stays
 * primary either way.
 */
export default function ProfileActions({ user, onChanged }: ProfileActionsProps) {
  const navigate = useNavigate()
  const sessionUser = useAuthStore((state) => state.user)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [starting, setStarting] = useState(false)

  const hasContact = user.is_contact_visible === true
  const secondAction: 'message' | 'contact' = hasContact ? 'contact' : 'message'

  async function openMessage() {
    if (starting) return
    setStarting(true)
    try {
      const { conversation } = await chatApi.startDm(user.id)
      navigate(`/chat/${conversation.id}`)
    } catch {
      // A premium user cannot DM a stranger without a subscription; the
      // backend answers 403 and there is nothing to navigate to.
      setStarting(false)
      return
    }
    setStarting(false)
  }

  return (
    <>
      <div className="action-pair mt-4">
        <FollowButton
          user={user}
          onChanged={() => onChanged?.()}
        />
        {secondAction === 'contact' ? (
          <button
            type="button"
            onClick={() => setSheetOpen(true)}
            className="btn-outline"
          >
            Contact
          </button>
        ) : (
          <button
            type="button"
            onClick={() => void openMessage()}
            disabled={starting}
            className="btn-outline disabled:opacity-60"
          >
            {starting ? 'Opening…' : 'Message'}
          </button>
        )}
        {/* Blocking yourself is not an option, so the menu is for other people only. */}
        {sessionUser?.id !== user.id ? (
          <BlockMenu user={user} onBlocked={onChanged} />
        ) : null}
      </div>

      {sheetOpen ? (
        <ContactSheet user={user} onClose={() => setSheetOpen(false)} />
      ) : null}
    </>
  )
}
