import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { authApi, type AuthPayload, type AuthUser } from '../lib/api'

interface SessionState {
  token: string | null
  admin: AuthUser | null
  login: (identifier: string, password: string) => Promise<{ superseded: boolean }>
  logout: () => Promise<void>
}

export const useAdminStore = create<SessionState>()(
  persist(
    (set) => ({
      token: null,
      admin: null,
      login: async (identifier, password) => {
        const payload: AuthPayload & { superseded?: boolean } = await authApi.login(
          identifier,
          password,
        )
        set({ token: payload.access_token, admin: payload.user })
        return { superseded: payload.superseded ?? false }
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