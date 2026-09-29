/**
 * Mirrors `App\Domain\Moderation\Enums` on the server.
 *
 * The reason list is deliberately *not* hardcoded here. The picker fetches
 * `/moderation/report-reasons` and renders what comes back, so a reason added
 * on the server appears in the client without a release, and the client cannot
 * offer a reason the server would reject.
 */

export type ReportTargetType = 'user' | 'post' | 'comment' | 'message'

export type ReportStatus = 'pending' | 'reviewing' | 'actioned' | 'dismissed'

export interface ReportReason {
  value: string
  label: string
  /** True for the reasons that put someone at risk; surfaced first in the queue. */
  is_urgent: boolean
}

export interface ReportTargetTypeSummary {
  value: ReportTargetType
  label: string
}

export interface ReportReasonOptions {
  reasons: ReportReason[]
  target_types: ReportTargetTypeSummary[]
}

/** What the reporter gets back: an acknowledgement, not the record. */
export interface ReportReceipt {
  id: number
  target_type: ReportTargetType
  target_type_label: string
  status: ReportStatus
  created_at: string | null
}

export interface ReportInput {
  target_type: ReportTargetType
  target_id: number
  reason: string
  details?: string | null
}
