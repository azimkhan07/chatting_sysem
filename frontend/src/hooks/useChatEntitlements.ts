import { useQuery } from '@tanstack/react-query'

import { chatApi } from '@/lib/api'
import type { ChatEntitlements, ChatFeature, ChatFeatureKey } from '@/types/chat'

/**
 * The crown/lock source of truth for the chat UI.
 *
 * The backend is authoritative — `EnsureChatFeature` rejects a locked call
 * regardless of what the client believed — so this is only used to render the
 * right affordance and to name the right feature in the upgrade prompt. A
 * failure here must not hide the rest of the chat, so it degrades to "nothing
 * unlocked" and the user discovers the lock on first use.
 */
export function useChatEntitlements() {
  const query = useQuery({
    queryKey: ['chat', 'entitlements'],
    queryFn: chatApi.entitlements,
    staleTime: 60_000,
  })

  const unlocked = new Set<ChatFeatureKey>(
    (query.data?.unlocked ?? []) as ChatFeatureKey[],
  )

  return {
    ...query,
    unlocked,
    isUnlocked: (feature: ChatFeatureKey) => unlocked.has(feature),
    feature: (key: ChatFeatureKey): ChatFeature | undefined =>
      query.data?.features.find((item) => item.key === key),
    wallpapers: query.data?.wallpapers ?? [],
  } satisfies {
    unlocked: Set<ChatFeatureKey>
    isUnlocked: (feature: ChatFeatureKey) => boolean
    feature: (key: ChatFeatureKey) => ChatFeature | undefined
    wallpapers: ChatEntitlements['wallpapers']
  } & Pick<ReturnType<typeof useQuery<ChatEntitlements>>, 'data' | 'isPending' | 'isError'>
}
