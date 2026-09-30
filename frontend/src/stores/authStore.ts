import { create } from 'zustand'
import { persist } from 'zustand/middleware'

import { api } from '@/lib/api'
import { usePresenceStore } from '@/stores/presenceStore'
import type { LoginInput, RegisterInput, User } from '@/types/user'

type AuthStatus = 'idle' | 'loading' | 'guest' | 'authenticated'

interface AppealInput extends LoginInput {
  message: string
}

interface AuthState {
  token: string | null
  user: User | null
  status: AuthStatus
  login: (input: LoginInput) => Promise<void>
  /**
   * Wakes a self-deactivated account and signs in, in one round trip.
   *
   * The backend reactivation endpoint issues a token like sign-in does, so
   * there is no second login call to make afterwards.
   */
  reactivate: (input: LoginInput) => Promise<void>
  register: (input: RegisterInput) => Promise<void>
  /** Files an appeal for a suspended account; does not sign in. */
  appeal: (input: AppealInput) => Promise<{ id: number; status: string }>
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

      async reactivate(input) {
        set({ status: 'loading' })
        try {
          const data = await api.post<AuthResponseData>('/auth/reactivate', input)
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

      async appeal(input) {
        set({ status: 'loading' })
        try {
          const data = await api.post<{
            appeal: { id: number; status: string }
          }>('/auth/appeal', input)
          return data.appeal
        } catch (error) {
          throw error
        } finally {
          // The appeal does not authenticate the user, so the session stays
          // guest; only reset the loading state.
          set({ status: 'guest' })
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
          usePresenceStore.getState().reset()
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