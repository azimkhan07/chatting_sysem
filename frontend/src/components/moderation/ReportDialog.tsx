import { useMutation, useQuery } from '@tanstack/react-query'
import { useState } from 'react'

import { moderationApi } from '@/lib/api'
import type { ReportTargetType } from '@/types/moderation'

interface ReportDialogProps {
  targetType: ReportTargetType
  targetId: number
  /** What is being reported, in the reporter's words: "post by @tanya". */
  subject: string
  onClose: () => void
}

/**
 * One dialog for reporting a post, comment, message or account.
 *
 * The reason list comes from the server rather than living here. That is not
 * cosmetic: the list doubles as the moderator's triage filter, so a client
 * copy of it would drift and start offering reasons the server rejects, and
 * would hide new ones until the app shipped again.
 *
 * Reported only from a place the reporter can already see the target. The
 * server re-checks that (a message id from a conversation you are not in is
 * rejected), so this is a convenience, not the security boundary.
 */
export default function ReportDialog({ targetType, targetId, subject, onClose }: ReportDialogProps) {
  const [reason, setReason] = useState<string | null>(null)
  const [details, setDetails] = useState('')

  const { data } = useQuery({
    queryKey: ['moderation', 'report-reasons'],
    queryFn: () => moderationApi.reportReasons(),
    // The list is small and changes on a deploy schedule, not per minute.
    staleTime: 10 * 60_000,
  })

  const submit = useMutation({
    mutationFn: () =>
      moderationApi.report({
        target_type: targetType,
        target_id: targetId,
        reason: reason as string,
        details: details.trim() || null,
      }),
    onSuccess: onClose,
  })

  const reasons = data?.reasons ?? []
  const selected = reasons.find((item) => item.value === reason)

  return (
    <div className="fixed inset-0 z-[100] flex items-end justify-center bg-black/70 p-0 sm:items-center sm:p-4">
      <div className="w-full max-w-md rounded-t-3xl border border-white/10 bg-slate-900 p-5 sm:rounded-3xl">
        <div className="mb-4 flex items-start justify-between gap-4">
          <div>
            <h2 className="text-sm font-semibold text-white">Report {subject}</h2>
            <p className="mt-1 text-xs text-slate-400">
              Reports are reviewed by our team. We will not tell the account who reported them.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close report dialog"
            className="rounded-full p-1 text-slate-400 transition hover:bg-white/5 hover:text-white"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M18 6 6 18M6 6l12 12"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
              />
            </svg>
          </button>
        </div>

        <div className="max-h-64 space-y-1 overflow-y-auto">
          {reasons.map((item) => (
            <label
              key={item.value}
              className={`flex cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm transition ${
                reason === item.value
                  ? 'bg-amber-500/15 text-amber-200 ring-1 ring-amber-500/40'
                  : 'text-slate-300 hover:bg-white/5'
              }`}
            >
              <span>{item.label}</span>
              {item.is_urgent ? (
                <span className="rounded-full bg-rose-500/20 px-2 py-0.5 text-[10px] font-medium text-rose-300">
                  Reviewed first
                </span>
              ) : null}
              <input
                type="radio"
                name="report-reason"
                value={item.value}
                checked={reason === item.value}
                onChange={() => setReason(item.value)}
                className="sr-only"
              />
            </label>
          ))}
        </div>

        <textarea
          value={details}
          onChange={(event) => setDetails(event.target.value)}
          rows={3}
          maxLength={1000}
          placeholder="Add anything that would help (optional)"
          className="mt-3 w-full resize-none rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-amber-500/50 focus:outline-none"
        />

        {submit.isError ? (
          <p className="mt-2 text-xs text-rose-400">
            Could not send that report. Please try again.
          </p>
        ) : null}

        <div className="mt-4 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className="rounded-full px-4 py-2 text-sm text-slate-400 transition hover:text-white"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={!reason || submit.isPending}
            onClick={() => submit.mutate()}
            className="rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-slate-950 transition disabled:opacity-40"
          >
            {submit.isPending ? 'Sending…' : 'Submit report'}
          </button>
        </div>

        {selected?.is_urgent ? (
          <p className="mt-3 rounded-xl bg-rose-500/10 px-3 py-2 text-xs text-rose-300">
            If someone is in immediate danger, please also contact your local emergency services.
          </p>
        ) : null}
      </div>
    </div>
  )
}
