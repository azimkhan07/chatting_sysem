export interface StoryFilter {
  key: string
  name: string
  css: string
}

export const STORY_FILTERS: StoryFilter[] = [
  { key: 'none', name: 'Normal', css: 'none' },
  { key: 'clarendon', name: 'Clarendon', css: 'saturate(1.4) contrast(1.12) brightness(1.04)' },
  { key: 'gingham', name: 'Gingham', css: 'brightness(1.05) contrast(0.95) saturate(0.85)' },
  { key: 'lark', name: 'Lark', css: 'brightness(1.09) saturate(0.72) contrast(0.93)' },
  { key: 'juno', name: 'Juno', css: 'saturate(1.42) contrast(1.1) brightness(0.96)' },
  { key: 'slumber', name: 'Slumber', css: 'brightness(0.9) saturate(0.66) contrast(0.92) sepia(0.12)' },
  { key: 'crema', name: 'Crema', css: 'brightness(1.06) saturate(0.9) contrast(0.92) sepia(0.1)' },
  { key: 'moon', name: 'Moon', css: 'grayscale(1) contrast(1.15) brightness(1.08)' },
  { key: 'willow', name: 'Willow', css: 'grayscale(0.9) contrast(0.95) brightness(1.05)' },
]

export function filterCss(effects: string | null | undefined): string {
  const filter = STORY_FILTERS.find((item) => item.key === effects)
  return filter?.css ?? 'none'
}