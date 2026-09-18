import { motion } from 'framer-motion'
import { useEffect, useState } from 'react'

import { THEME_FLASH_EVENT } from '@/lib/theme'

interface Wash {
  key: number
  theme: 'light' | 'dark'
}

export default function ThemeWash() {
  const [wash, setWash] = useState<Wash | null>(null)

  useEffect(() => {
    const handler = (event: Event) => {
      const { theme } = (event as CustomEvent<{ theme: 'light' | 'dark' }>).detail
      setWash({ key: Date.now(), theme })
    }
    window.addEventListener(THEME_FLASH_EVENT, handler)
    return () => window.removeEventListener(THEME_FLASH_EVENT, handler)
  }, [])

  if (!wash) return null

  const background = wash.theme === 'dark' ? '#070812' : '#f2f4fb'

  return (
    <motion.div
      key={wash.key}
      initial={{ opacity: 0.85, scale: 0.35, borderRadius: '9999px' }}
      animate={{ opacity: 0, scale: 3.4, borderRadius: '9999px' }}
      transition={{ duration: 0.55, ease: 'easeOut' }}
      onAnimationComplete={() => setWash(null)}
      className="pointer-events-none fixed inset-0 z-[70]"
      style={{ background, transformOrigin: 'center' }}
      aria-hidden="true"
    />
  )
}