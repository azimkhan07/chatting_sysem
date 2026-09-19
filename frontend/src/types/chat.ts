export type ConversationType = 'dm' | 'group'
export type MemberRole = 'owner' | 'admin' | 'member'
export type MessageKind = 'text' | 'image' | 'video'

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