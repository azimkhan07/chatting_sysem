import { create } from 'zustand'
import { persist } from 'zustand/middleware'

import { api } from '@/lib/api'
import type { LoginInput, RegisterInput, User } from '@/types/user'

type AuthStatus = 'idle' | 'loading' | 'guest' | 'authenticated'

interface AuthState {
  token: string | null
  user: User | null
  status: AuthStatus
  login: (input: LoginInput) => Promise<void>
  register: (input: RegisterInput) => Promise<void>
  logout: () => Promise<void>
  setUser: (user: User | null) => void
}

interface AuthResponseData {
  access_token: string
  token_type: string
  expires_in: number
  user: User
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      status: 'idle',

      async login(input) {
        set({ status: 'loading' })
        try {
          const data = await api.post<AuthResponseData>('/auth/login', input)
          set({
            token: data.access_token,
            user: data.user,
            status: 'authenticated',
          })
        } catch (error) {
          set({ status: 'guest' })
          throw error
        }
      },

      async register(input) {
        set({ status: 'loading' })
        try {
          const data = await api.post<AuthResponseData>('/auth/register', input)
          set({
            token: data.access_token,
            user: data.user,
            status: 'authenticated',
          })
        } catch (error) {
          set({ status: 'guest' })
          throw error
        }
      },

      async logout() {
        try {
          await api.post<void>('/auth/logout', {})
        } catch {
          // Local state is cleared regardless of network outcome.
        } finally {
          set({ token: null, user: null, status: 'guest' })
        }
      },

      setUser(user) {
        set({ user, status: user ? 'authenticated' : 'guest' })
      },
    }),
    {
      name: 'amtechat.auth',
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        status: state.status,
      }),
    },
  ),
)