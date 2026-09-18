import type { User } from '@/types/user'

export interface PostMedia {
  id: number
  type: 'image' | 'video'
  url: string
  mime: string
  width: number | null
  height: number | null
  duration: number | null
}

export interface Post {
  id: number
  body: string
  author: User
  media: PostMedia[]
  created_at: string
}

export interface FeedPage {
  posts: Post[]
  next_cursor: string | null
}