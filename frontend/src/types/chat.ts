import type { ReactionName } from '@/lib/reactions'

export type ConversationType = 'dm' | 'group'
export type MemberRole = 'owner' | 'admin' | 'member'
export type MessageKind = 'text' | 'image' | 'video'

export type MessageReactionName = ReactionName

export interface ChatMember {
  user: {
    id: number
    username: string
    display_name: string
    avatar_url: string | null
  }
  role: MemberRole
}

export interface ChatMessageSender {
  id: number
  username: string
  display_name: string
  avatar_url: string | null
  is_verified: boolean
}

export interface ConversationMessage {
  id: number
  conversation_id: number
  sender: ChatMessageSender | null
  type: MessageKind
  body: string | null
  media_url: string | null
  read: boolean
  reactions: Record<ReactionName, number>
  my_reaction: ReactionName | null
  created_at: string
  /** Present on locally-created, not-yet-persisted messages. */
  client_id?: string
}

export interface Conversation {
  id: number
  type: ConversationType
  display_name: string
  avatar_url: string | null
  peer_verified: boolean | null
  members_count: number
  members: ChatMember[]
  last_message: ConversationMessage | null
  unread_count: number
  muted: boolean
  updated_at: string
}

export interface ChatMessagesPage {
  messages: ConversationMessage[]
  next_cursor: string | null
}

export interface ChatUnreadTotal {
  unread: number
}

export interface MessageReactionResult {
  message_id: number
  reaction: ReactionName | null
  reactions: Record<ReactionName, number>
}

export interface RealtimeReactionPayload {
  conversation_id: number
  message_id: number
  user_id: number
  reaction: ReactionName | null
  totals: Record<ReactionName, number>
}

export interface RealtimeDeletedPayload {
  conversation_id: number
  message_id: number
  deleted_by: number
}

export interface RealtimeMessagePayload {
  conversation_id: number
  message: ConversationMessage
}

export interface RealtimeTypingPayload {
  conversation_id: number
  user_id: number
}