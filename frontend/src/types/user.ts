export type UserStatus = 'active' | 'suspended' | 'blocked'

export interface User {
  id: number
  username: string
  display_name: string
  email: string | null
  mobile: string | null
  avatar_url: string | null
  is_verified: boolean
  status: UserStatus
  created_at: string
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