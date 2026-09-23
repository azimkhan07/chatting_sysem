export const THREAD_REACTIONS = ['like', 'love', 'haha', 'wow', 'sad', 'angry'] as const

export type ThreadReactionName = (typeof THREAD_REACTIONS)[number]

export interface ThreadEntryUser {
  id: number
  username: string
  display_name: string
  avatar_url: string | null
}

export interface ThreadEntry {
  id: number
  thread_id: number
  user: ThreadEntryUser | null
  body: string | null
  media_url: string | null
  media_type: string | null
  reactions: Record<ThreadReactionName, number>
  my_reaction: ThreadReactionName | null
  created_at: string
}

export interface ThreadRecap {
  entries: number
  participants: number
  reactions: Record<string, number>
  top_contributor: {
    id: number
    username: string
    display_name: string
    avatar_path: string | null
    entries: number
  } | null
}

export interface Thread {
  id: number
  conversation_id: number
  status: 'active' | 'expired'
  active: boolean
  created_by: number
  expires_at: string | null
  entry_count: number
  recap: ThreadRecap | null
  created_at: string
}

export interface ThreadDetail {
  thread: Thread
  entries: ThreadEntry[]
}

export interface ThreadReactionResult {
  reacted: boolean
  reaction: ThreadReactionName | null
  totals: Record<ThreadReactionName, number>
}

export interface RealtimeThreadEntryPayload {
  thread_id: number
  conversation_id: number
  entry: ThreadEntry
}

export interface RealtimeThreadReactionPayload {
  thread_id: number
  conversation_id: number
  entry_id: number
  user_id: number
  reaction: ThreadReactionName | null
  reacted: boolean
  totals: Record<ThreadReactionName, number>
}