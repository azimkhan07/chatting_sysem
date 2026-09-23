import { create } from 'zustand'
import { persist } from 'zustand/middleware'

import { adminApi } from '@/lib/api'
import type { AuthPayload, LoginInput, User } from '@/types/user'

type AdminStatus = 'idle' | 'loading' | 'guest' | 'authenticated'

interface AdminState {
  token: string | null
  admin: User | null
  status: AdminStatus
  login: (input: LoginInput) => Promise<void>
  logout: () => Promise<void>
}

export const useAdminStore = create<AdminState>()(
  persist(
    (set) => ({
      token: null,
      admin: null,
      status: 'idle',

      async login(input) {
        set({ status: 'loading' })
        try {
          const data = await adminApi.post<AuthPayload>('/auth/login', input)
          set({
            token: data.access_token,
            admin: data.user,
            status: 'authenticated',
          })
        } catch (error) {
          set({ status: 'guest' })
          throw error
        }
      },

      async logout() {
        try {
          await adminApi.post<void>('/auth/logout', {})
        } catch {
          // Admin state is cleared regardless of the network outcome.
        } finally {
          set({ token: null, admin: null, status: 'guest' })
        }
      },
    }),
    {
      name: 'amtechat.admin',
      partialize: (state) => ({
        token: state.token,
        admin: state.admin,
        status: state.status,
      }),
    },
  ),
)