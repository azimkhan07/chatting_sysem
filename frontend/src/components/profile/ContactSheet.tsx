import { useState } from 'react'

import type { User } from '@/types/user'

interface ContactSheetProps {
  user: User
  onClose: () => void
}

/**
 * Contact options for a business or professional account.
 *
 * The point of the sheet is that the *visitor* chooses the channel — WhatsApp
 * or email — rather than the account owner pushing one. Only the channels the
 * owner actually published are offered, so a one-method business shows one row
 * and needs no sheet at all.
 */
export function ContactSheet({ user, onClose }: ContactSheetProps) {
  const [copied, setCopied] = useState(false)

  const phone = user.contact_phone?.trim() ?? ''
  const email = user.contact_email?.trim() ?? ''

  // wa.me needs country code digits only; a stored "+91 98765 43210" still works
  // because it strips everything that is not a digit.
  const whatsappDigits = phone.replace(/\D/g, '')

  async function copyEmail() {
    try {
      await navigator.clipboard.writeText(email)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 1800)
    } catch {
      window.location.href = `mailto:${email}`
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-end bg-black/60 p-0 backdrop-blur-sm sm:place-items-center sm:p-4"
      onClick={onClose}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-label={`Contact ${user.display_name}`}
        onClick={(event) => event.stopPropagation()}
        className="surface-raised w-full max-w-sm rounded-t-3xl border p-5 shadow-2xl sm:rounded-3xl"
      >
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
              {user.account_type_label ?? 'Business'}
            </p>
            <h2 className="truncate text-base font-semibold text-slate-100">
              Contact {user.display_name}
            </h2>
            <p className="mt-0.5 text-xs text-slate-400">
              Pick how you want to reach them — nothing is sent automatically.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/5"
          >
            ✕
          </button>
        </div>

        <div className="mt-4 space-y-2">
          {phone ? (
            <a
              href={`https://wa.me/${whatsappDigits}`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 rounded-xl border border-[var(--surface-border)] bg-[var(--surface-sunken)] p-3.5 transition hover:border-[#25D366]/60"
            >
              <WhatsAppGlyph />
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold text-slate-100">
                  Message on WhatsApp
                </span>
                <span className="block truncate text-xs text-slate-500">{phone}</span>
              </span>
              <span className="text-xs font-semibold text-[#25D366]">Open</span>
            </a>
          ) : null}

          {email ? (
            <div className="flex items-center gap-3 rounded-xl border border-[var(--surface-border)] bg-[var(--surface-sunken)] p-3.5">
              <MailGlyph />
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold text-slate-100">Send an email</span>
                <span className="block truncate text-xs text-slate-500">{email}</span>
              </span>
              <div className="flex shrink-0 items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => void copyEmail()}
                  className="rounded-lg border border-[var(--surface-border)] px-2.5 py-1 text-xs font-semibold text-slate-300 transition hover:bg-white/5"
                >
                  {copied ? 'Copied' : 'Copy'}
                </button>
                <a
                  href={`mailto:${email}`}
                  className="rounded-lg bg-brand-500/15 px-2.5 py-1 text-xs font-semibold text-brand-200 transition hover:bg-brand-500/25"
                >
                  Open
                </a>
              </div>
            </div>
          ) : null}
        </div>

        <p className="mt-4 text-[11px] leading-relaxed text-slate-500">
          This account chose to publish these details. It is not linked to a chat, so nothing here is
          read receipt tracked.
        </p>
      </div>
    </div>
  )
}

function WhatsAppGlyph() {
  return (
    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#25D366]/15 text-[#25D366]">
      <svg viewBox="0 0 24 24" fill="currentColor" className="h-5 w-5" aria-hidden="true">
        <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.17 8.17 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23a8.2 8.2 0 0 1 5.83 2.42 8.16 8.16 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.15.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.44.13-.15.17-.25.25-.42.08-.16.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.23.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.2-.58.2-1.08.14-1.18-.06-.11-.23-.17-.48-.29Z" />
      </svg>
    </span>
  )
}

function MailGlyph() {
  return (
    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500/15 text-brand-200">
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        className="h-5 w-5"
        aria-hidden="true"
      >
        <rect x="3" y="5" width="18" height="14" rx="2.5" />
        <path d="m4 7 8 6 8-6" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    </span>
  )
}
