/**
 * Mirrors `App\Domain\Auth\Enums\UserStatus` on the server.
 *
 * `banned` is an admin action and `suspended` is the softer admin state; neither
 * is reversible by the user, which is why both are distinct from a
 * self-service deactivation (`deactivated_at` on the account, no status change).
 */
export type UserStatus = 'active' | 'suspended' | 'banned'

/**
 * Personal accounts are message + follow only. Professional and business
 * accounts may publish a WhatsApp number or an email that a visitor can use
 * instead of opening a chat.
 */
export type AccountType = 'personal' | 'professional' | 'business'

/** Creator/business category, mirroring `ProfileCategory` on the server. */
export type ProfileCategoryKey =
  | 'artist'
  | 'entertainment'
  | 'sport'
  | 'creator'
  | 'music'
  | 'food'
  | 'fashion'
  | 'beauty'
  | 'travel'
  | 'tech'
  | 'education'
  | 'business'
  | 'other'

/**
 * A user from the composer's "@" picker.
 *
 * The flag is not decoration: it is the server telling the client which of the
 * two ranked groups this row came from, so the list can label the first group
 * "Following" and draw the divider between them.
 */
export type MentionSuggestion = User & {
  is_following: boolean
}

export interface User {
  id: number
  username: string
  display_name: string
  bio: string | null
  email: string | null
  mobile: string | null
  avatar_url: string | null
  cover_url: string | null
  is_verified: boolean
  status: UserStatus
  created_at: string
  account_type?: AccountType
  account_type_label?: string
  /** Public account: visible to everyone. Private: only approved followers. */
  is_private?: boolean
  /** Creator/business category key, e.g. "artist"; null on personal accounts. */
  category?: ProfileCategoryKey | null
  /** Human label of `category`, e.g. "Artist". */
  category_label?: string | null
  /** Only the owner ever sees these unless they are published. */
  contact_email?: string | null
  contact_phone?: string | null
  show_contact?: boolean
  is_contact_visible?: boolean
  posts_count?: number
  followers_count?: number
  following_count?: number
  is_followed_by_me?: boolean
}

export interface PublicUser {
  user: User
}

export interface FollowResult {
  following: boolean
  followers_count: number
}

export interface UserPage {
  users: User[]
  next_cursor: string | null
}

export interface AuthPayload {
  access_token: string
  token_type: string
  expires_in: number
  user: User
}

export interface RegisterInput {
  username: string
  display_name: string
  email?: string
  mobile?: string
  password: string
  password_confirmation: string
}

export interface LoginInput {
  identifier: string
  password: string
}