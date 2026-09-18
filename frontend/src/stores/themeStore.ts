import { create } from 'zustand'

import {
  applyTheme,
  getStoredTheme,
  resolveTheme,
  THEME_FLASH_EVENT,
  type ResolvedTheme,
  type ThemeMode,
} from '@/lib/theme'

interface ThemeState {
  mode: ThemeMode
  resolved: ResolvedTheme
  setMode: (mode: ThemeMode) => void
}

function initialState(): Pick<ThemeState, 'mode' | 'resolved'> {
  const mode = getStoredTheme()
  return { mode, resolved: resolveTheme(mode) }
}

export const useThemeStore = create<ThemeState>((set) => ({
  ...initialState(),

  setMode: (mode) => {
    const resolved = resolveTheme(mode)
    applyTheme(mode)

    const root = document.documentElement
    root.classList.add('theme-switching')
    window.setTimeout(() => root.classList.remove('theme-switching'), 450)

    set({ mode, resolved })
    window.dispatchEvent(
      new CustomEvent(THEME_FLASH_EVENT, { detail: { theme: resolved } }),
    )
  },
}))

export function initTheme() {
  applyTheme(getStoredTheme())

  // Keep `system` in sync when the OS preference changes.
  const media = window.matchMedia('(prefers-color-scheme: dark)')
  const sync = () => {
    const mode = useThemeStore.getState().mode
    if (mode === 'system') {
      const resolved = resolveTheme('system')
      applyTheme('system')
      useThemeStore.setState({ resolved })
    }
  }
  try {
    media.addEventListener('change', sync)
  } catch {
    // Older engines use the deprecated listener API.
    media.addListener(sync)
  }
}