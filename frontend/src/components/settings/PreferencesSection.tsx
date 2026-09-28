import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { SettingCard } from '@/components/settings/SettingCard'
import { Toggle } from '@/components/settings/SettingsUI'
import { settingsApi } from '@/lib/api'
import type { NotificationKey, PrivacyKey, SettingsPatch, UserSettings } from '@/types/settings'

interface Item<K extends string> {
  key: K
  label: string
  hint: string
}

/**
 * The copy is the point.
 *
 * "Discoverable" means nothing to a user; "Let people find you in search and
 * Explore" does. Each hint says what turns off, not just what the switch is
 * called.
 *
 * Each list is typed to its own group's keys, so a notification switch cannot
 * be added to the privacy block and still compile.
 */
const PRIVACY: Item<PrivacyKey>[] = [
  {
    key: 'discoverable',
    label: 'Discoverable',
    hint: 'Let people find you in search and on the Explore page.',
  },
  {
    key: 'show_activity_status',
    label: 'Show when I was last active',
    hint: 'People can see whether you are online and when you were last seen.',
  },
  {
    key: 'allow_message_requests',
    label: 'Allow message requests',
    hint: 'Turn off to stop new chats from strangers. Existing chats keep working.',
  },
  {
    key: 'allow_tagging',
    label: 'Allow me to be tagged',
    hint: 'People can tag you in posts and comments.',
  },
]

const NOTIFICATIONS: Item<NotificationKey>[] = [
  { key: 'notify_messages', label: 'New messages', hint: 'Someone sent you a message.' },
  { key: 'notify_requests', label: 'Message requests', hint: 'A stranger wants to start a chat.' },
  { key: 'notify_follows', label: 'New followers', hint: 'Someone started following you.' },
  { key: 'notify_likes', label: 'Likes', hint: 'Someone liked your post.' },
  { key: 'notify_comments', label: 'Comments and replies', hint: 'Someone replied to you.' },
]

/**
 * One preference group, split out because Privacy and Notifications are
 * separate rail entries now — someone looking for "who can message me" should
 * not have to scroll past every notification switch to reach it.
 *
 * They still share one query and one mutation, because the server stores them
 * on the same row: two independent fetches of a single resource would be a
 * request that has to be kept consistent with itself.
 */
function usePreferences() {
  const queryClient = useQueryClient()

  const query = useQuery({
    queryKey: ['settings', 'preferences'],
    queryFn: settingsApi.show,
    // Writes go through the mutation's own cache update, so a refetch on every
    // window focus would fight the switch the user just flipped.
    staleTime: 60_000,
  })

  const save = useMutation({
    mutationFn: (input: SettingsPatch) => settingsApi.update(input),
    onMutate: async (input) => {
      await queryClient.cancelQueries({ queryKey: ['settings', 'preferences'] })
      const previous = queryClient.getQueryData(['settings', 'preferences'])

      // Optimistic, because a toggle that visibly lags a second feels broken.
      queryClient.setQueryData<{ settings: UserSettings }>(
        ['settings', 'preferences'],
        (old) =>
          old
            ? {
                settings: {
                  privacy: { ...old.settings.privacy, ...input.privacy },
                  notifications: { ...old.settings.notifications, ...input.notifications },
                },
              }
            : old,
      )

      return { previous }
    },
    onError: (_error, _input, context) => {
      if (context?.previous) {
        queryClient.setQueryData(['settings', 'preferences'], context.previous)
      }
    },
  })

  return { query, save, settings: query.data?.settings }
}

export function PrivacySection() {
  const { query, save, settings } = usePreferences()

  return (
    <SettingCard
      title="Privacy"
      description="Who can find you, message you and tag you. Turning something off here never deletes anything you already did."
    >
      {query.isPending ? <Loading /> : null}

      {settings ? (
        <div className="divide-y divide-white/5">
          {PRIVACY.map((item) => (
            <Toggle
              key={item.key}
              label={item.label}
              hint={item.hint}
              checked={settings.privacy[item.key]}
              busy={save.isPending}
              onChange={(next) => save.mutate({ privacy: { [item.key]: next } })}
            />
          ))}
        </div>
      ) : null}

      {query.isError ? <ErrorNote>Could not load your privacy settings.</ErrorNote> : null}
      {save.isError ? (
        <ErrorNote>
          That change did not save. {save.error instanceof Error ? save.error.message : ''}
        </ErrorNote>
      ) : null}
    </SettingCard>
  )
}

export function NotificationsSection() {
  const { query, save, settings } = usePreferences()

  return (
    <SettingCard
      title="Notifications"
      description="Choose what amteCHAT is allowed to interrupt you for."
    >
      {query.isPending ? <Loading /> : null}

      {settings ? (
        <div className="divide-y divide-white/5">
          {NOTIFICATIONS.map((item) => (
            <Toggle
              key={item.key}
              label={item.label}
              hint={item.hint}
              checked={settings.notifications[item.key]}
              busy={save.isPending}
              onChange={(next) => save.mutate({ notifications: { [item.key]: next } })}
            />
          ))}
        </div>
      ) : null}

      {query.isError ? <ErrorNote>Could not load your notification settings.</ErrorNote> : null}
      {save.isError ? (
        <ErrorNote>
          That change did not save. {save.error instanceof Error ? save.error.message : ''}
        </ErrorNote>
      ) : null}
    </SettingCard>
  )
}

function Loading() {
  return (
    <div className="grid place-items-center py-8">
      <Spinner className="h-5 w-5" />
    </div>
  )
}

function ErrorNote({ children }: { children: ReactNode }) {
  return <p className="mt-2 text-xs text-rose-300">{children}</p>
}
