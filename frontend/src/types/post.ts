import type { User } from '@/types/user'

export interface Post {
  id: number
  body: string
  author: User
  created_at: string
}

export interface FeedPage {
  posts: Post[]
  next_cursor: string | null
}