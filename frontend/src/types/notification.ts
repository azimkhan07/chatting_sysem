import type { User } from '@/types/user'

export type NotificationType = 'follow' | 'like' | 'comment'

export interface Notification {
  id: number
  type: NotificationType
  data: {
    post_id?: number
    comment_preview?: string
  }
  read_at: string | null
  created_at: string
  actor: User
}

export interface NotificationsPage {
  notifications: Notification[]
  next_cursor: string | null
}

export interface UnreadCountResult {
  unread: number
}