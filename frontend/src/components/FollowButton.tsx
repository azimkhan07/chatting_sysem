import { useState } from 'react'

import { usersApi } from '@/lib/api'
import type { User } from '@/types/user'

interface FollowButtonProps {
  user: User
  onChanged?: (result: { following: boolean; followers_count: number }) => void
}

export default function FollowButton({ user, onChanged }: FollowButtonProps) {
  const [following, setFollowing] = useState(user.is_followed_by_me ?? false)
  const [pending, setPending] = useState(false)

  async function toggle() {
    if (pending) return
    setPending(true)
    try {
      const result = following
        ? await usersApi.unfollow(user.username)
        : await usersApi.follow(user.username)
      setFollowing(result.following)
      onChanged?.(result)
    } catch {
      // Keep prior state; no toast for now.
    } finally {
      setPending(false)
    }
  }

  return (
    <button
      type="button"
      onClick={() => void toggle()}
      disabled={pending}
      className={
        following
          ? 'btn-secondary w-full disabled:opacity-60'
          : 'btn-primary w-full disabled:opacity-60'
      }
    >
      {pending ? '…' : following ? 'Following' : 'Follow'}
    </button>
  )
}