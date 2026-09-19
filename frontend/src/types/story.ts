import type { Song } from '@/types/song'
import type { User } from '@/types/user'

export type StoryMediaType = 'image' | 'video'

export interface TextStyle {
  font?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  color?: 'white' | 'black' | 'yellow' | 'red' | 'green' | 'blue' | 'pink' | 'orange' | 'purple'
  align?: 'left' | 'center' | 'right'
  bg?: 'none' | 'solid' | 'gradient'
  pos?: 'top' | 'middle' | 'bottom'
}

export interface Story {
  id: number
  type: StoryMediaType
  url: string
  caption: string | null
  effects: string | null
  text_style: TextStyle | null
  song: Song | null
  created_at: string
  expires_at: string
}

export interface StoryGroup {
  user: User
  stories: Story[]
}

export interface GifResult {
  id: number | string
  url: string
  preview_url: string
  title: string
}