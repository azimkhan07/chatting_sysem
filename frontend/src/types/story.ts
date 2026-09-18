import type { User } from '@/types/user'

export type StoryMediaType = 'image' | 'video'

export interface Story {
  id: number
  type: StoryMediaType
  url: string
  caption: string | null
  effects: string | null
  created_at: string
  expires_at: string
}

export interface StoryGroup {
  user: User
  stories: Story[]
}