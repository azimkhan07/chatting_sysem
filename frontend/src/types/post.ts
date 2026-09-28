import type { Song } from '@/types/song'
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
  hashtags?: string[]
  /**
   * Free-text place name the author typed, not coordinates. Absent means the
   * author did not say, so the card renders no location line at all rather
   * than a placeholder.
   */
  location?: string | null
  /** The soundtrack, resolved from `song_id` on write. */
  song?: Song | null
  /**
   * Accounts this post says it is about, parsed from `@name` in the body.
   *
   * Only sent back on the post the author just created: the feed skips the
   * relation because it costs a query per post to draw a line most posts
   * will not have.
   */
  tagged_users?: User[]
  likes_count: number
  comments_count: number
  shares_count: number
  liked_by_me: boolean
  created_at: string
}

export interface Comment {
  id: number
  body: string
  author: User
  reply_count: number
  replies: Comment[]
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