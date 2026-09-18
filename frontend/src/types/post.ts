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
  hashtags: string[]
  likes_count: number
  comments_count: number
  liked_by_me: boolean
  created_at: string
}

export interface Comment {
  id: number
  body: string
  author: User
  created_at: string
}

export interface FeedPage {
  posts: Post[]
  next_cursor: string | null
}

export interface CommentsPage {
  comments: Comment[]
  next_cursor: string | null
}