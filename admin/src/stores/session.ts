import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { authApi, type AuthUser } from '../lib/api'

interface SessionState {
  token: string | null
  admin: AuthUser | null
  login: (identifier: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

export const useAdminStore = create<SessionState>()(
  persist(
    (set) => ({
      token: null,
      admin: null,
      login: async (identifier, password) => {
        const payload = await authApi.login(identifier, password)
        set({ token: payload.access_token, admin: payload.user })
      },
      logout: async () => {
        try {
          await authApi.logout()
        } catch {
          /* token already expired */
        }
        set({ token: null, admin: null })
      },
    }),
    { name: 'amtechat.admin' },
  ),
)