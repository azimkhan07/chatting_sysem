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