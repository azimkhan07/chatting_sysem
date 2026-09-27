import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { Panel, Toggle } from '@/components/settings/SettingsUI'
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

export function PreferencesSection({ open, onToggle }: { open: boolean; onToggle: () => void }) {
  const queryClient = useQueryClient()

  const settings = useQuery({
    queryKey: ['settings', 'preferences'],
    queryFn: settingsApi.show,
    enabled: open,
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

  const current = settings.data?.settings

  return (
    <Panel
      title="Preferences"
      caption="Privacy and notifications"
      open={open}
      onToggle={onToggle}
    >
      {settings.isPending ? (
        <div className="grid place-items-center py-6">
          <Spinner className="h-5 w-5" />
        </div>
      ) : null}

      {current ? (
        <>
          <PreferenceGroup title="Privacy">
            {PRIVACY.map((item) => (
              <Toggle
                key={item.key}
                label={item.label}
                hint={item.hint}
                checked={current.privacy[item.key]}
                busy={save.isPending}
                // One group per mutation: the endpoint is a partial update, so
                // sending both groups would overwrite a switch flipped a moment
                // earlier in the other group.
                onChange={(next) => save.mutate({ privacy: { [item.key]: next } })}
              />
            ))}
          </PreferenceGroup>

          <PreferenceGroup title="Notifications" divided>
            {NOTIFICATIONS.map((item) => (
              <Toggle
                key={item.key}
                label={item.label}
                hint={item.hint}
                checked={current.notifications[item.key]}
                busy={save.isPending}
                onChange={(next) => save.mutate({ notifications: { [item.key]: next } })}
              />
            ))}
          </PreferenceGroup>
        </>
      ) : null}

      {settings.isError ? (
        <p className="text-sm text-rose-300">Could not load your preferences.</p>
      ) : null}

      {save.isError ? (
        <p className="mt-2 text-xs text-rose-300">
          That change did not save. {save.error instanceof Error ? save.error.message : ''}
        </p>
      ) : null}
    </Panel>
  )
}

function PreferenceGroup({
  title,
  divided,
  children,
}: {
  title: string
  divided?: boolean
  children: ReactNode
}) {
  return (
    <div className={divided ? 'mt-4 border-t border-white/5 pt-3' : undefined}>
      <h3 className="px-2.5 text-[11px] font-bold tracking-wider text-slate-500 uppercase">
        {title}
      </h3>
      <div className="mt-1 divide-y divide-white/5">{children}</div>
    </div>
  )
}
