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