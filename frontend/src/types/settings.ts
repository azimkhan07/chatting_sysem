export type AccountType = 'personal' | 'professional' | 'business'

export type SettingsGroup = 'privacy' | 'notifications'

/** Mirrors `UserSettings::preferenceGroups()` on the server. */
export const SETTINGS_PREFERENCE_KEYS = {
  privacy: [
    'discoverable',
    'show_activity_status',
    'allow_message_requests',
    'allow_tagging',
  ],
  notifications: [
    'notify_messages',
    'notify_requests',
    'notify_follows',
    'notify_likes',
    'notify_comments',
  ],
} as const satisfies Record<SettingsGroup, readonly string[]>

export type PrivacyKey = (typeof SETTINGS_PREFERENCE_KEYS.privacy)[number]
export type NotificationKey = (typeof SETTINGS_PREFERENCE_KEYS.notifications)[number]
export type SettingsPreferenceKey = PrivacyKey | NotificationKey

/**
 * Keyed by group, and each group only holds its own keys.
 *
 * A flat `Record<SettingsPreferenceKey, boolean>` would typecheck but lie: it
 * would let the UI read `privacy.notify_likes` and get `undefined` at runtime,
 * which is exactly the bug this shape prevents.
 */
export type UserSettings = {
  privacy: Record<PrivacyKey, boolean>
  notifications: Record<NotificationKey, boolean>
}

export type SettingsPatch = {
  [K in SettingsGroup]?: Partial<Record<(typeof SETTINGS_PREFERENCE_KEYS)[K][number], boolean>>
}

export interface AccountCounts {
  posts: number
  followers: number
  following: number
}

export interface FamilyBadge {
  id: number
  name: string
  role: FamilyRole
  role_label: string
  is_owner: boolean
  members: number
}

export interface AccountOverview {
  username: string
  email: string | null
  mobile: string | null
  display_name: string
  account_type: AccountType
  status: 'active' | 'suspended' | 'banned'
  is_verified: boolean
  is_deactivated: boolean
  deactivated_at: string | null
  member_since: string
  last_seen_at: string | null
  counts: AccountCounts
  family: FamilyBadge | null
}

/** A signed-in device. The token value is never returned by the API. */
export interface Session {
  id: number
  name: string
  device: string
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
  is_current: boolean
}

// --- Family Center -----------------------------------------------------------

export type FamilyRole = 'guardian' | 'adult' | 'teen'

export interface FamilyRoleOption {
  value: FamilyRole
  label: string
  blurb: string
}

/**
 * What the *viewer* may do to this member. The server computes it per viewer,
 * so the client never re-derives a permission from its own role.
 */
export interface FamilyMemberPermissions {
  can_change_role: boolean
  can_remove: boolean
  can_leave: boolean
}

export interface FamilyMember {
  id: number
  role: FamilyRole
  role_label: string
  is_owner: boolean
  is_self: boolean
  user: {
    id: number
    username: string
    display_name: string
    avatar_path: string | null
    is_verified: boolean
  }
  joined_at: string | null
  permissions: FamilyMemberPermissions
}

export interface FamilyViewer {
  role: FamilyRole | null
  role_label: string | null
  is_owner: boolean
  can_manage_members: boolean
  can_add_guardian: boolean
  can_rename: boolean
  can_dissolve: boolean
  can_leave: boolean
  can_approve_spending: boolean
}

export interface Family {
  id: number
  name: string
  created_at: string | null
  counts: { members: number; guardians: number }
  viewer: FamilyViewer
  roles: FamilyRoleOption[]
  members: FamilyMember[]
}
