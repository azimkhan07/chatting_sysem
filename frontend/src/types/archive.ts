import type { Post } from '@/types/post'
import type { Story } from '@/types/story'

/** One day in the archive calendar. Present keys are those with content. */
export interface ArchiveDayCounts {
  posts: number
  stories: number
}

export interface ArchiveCalendar {
  year: number
  /** `YYYY-MM-DD` -> content counts. Days without content are absent. */
  days: Record<string, ArchiveDayCounts>
}

export interface ArchiveDayPosts {
  date: string
  posts: Post[]
}

export interface ArchiveDayStories {
  date: string
  stories: Story[]
}

export type SaveableType = 'post' | 'story'

export interface SavedCollection {
  id: number
  name: string
  item_count: number
  created_at: string | null
}

export interface SavedItem {
  id: number
  saveable_type: SaveableType
  saveable: Post | Story
  /** Collection ids this item was placed into, when loaded. */
  collections: number[]
  created_at: string | null
}

export interface SavedOverview {
  collections: SavedCollection[]
  items: SavedItem[]
  collection_counts: Record<number, number>
}

export interface SavedResult {
  saved: SavedItem
  created: boolean
}

export interface CollectionFolder {
  collection: SavedCollection
  items: SavedItem[]
}