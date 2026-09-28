import { useState } from 'react'

interface ContactMatchModalProps {
  open: boolean
  onClose: () => void
  onMatch: (numbers: string[]) => void
  busy: boolean
}

/**
 * Contact sync is opt-in and stateless: the user drops their numbers in, the
 * server matches them against registered accounts, and nothing is stored. The
 * browser cannot read a phone book without a picker we do not ship, so this
 * takes a pasted or uploaded list rather than pretending to.
 */
export function ContactMatchModal({ open, onClose, onMatch, busy }: ContactMatchModalProps) {
  const [raw, setRaw] = useState('')
  const [error, setError] = useState<string | null>(null)

  if (!open) return null

  function submit() {
    const numbers = raw
      .split(/[\n,;]+/)
      .map((value) => value.trim())
      .filter((value) => value.length > 0)

    if (numbers.length === 0) {
      setError('Add at least one phone number.')
      return
    }

    if (numbers.length > 500) {
      setError('That is more than 500 numbers — split the list.')
      return
    }

    setError(null)
    onMatch(numbers)
  }

  function onPickFile(file: File) {
    const reader = new FileReader()
    reader.onload = () => setRaw(String(reader.result ?? ''))
    reader.readAsText(file)
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4">
      <div className="glass-card w-full max-w-md p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-slate-100">Find my contacts</h2>
            <p className="mt-1 text-xs text-slate-400">
              Paste numbers or upload a CSV. They are matched and thrown away — nothing is stored.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg p-1 text-slate-400 transition hover:bg-white/5"
          >
            ✕
          </button>
        </div>

        <textarea
          value={raw}
          onChange={(event) => setRaw(event.target.value)}
          rows={6}
          placeholder={'+91 98765 43210\n9876543211\n919876543212'}
          className="input-field mt-4 resize-none font-mono text-xs"
        />

        <div className="mt-2 flex items-center justify-between gap-2">
          <label className="cursor-pointer text-xs font-semibold text-brand-200 transition hover:text-brand-100">
            Upload a .csv
            <input
              type="file"
              accept=".csv,text/csv,text/plain"
              className="hidden"
              onChange={(event) => {
                const file = event.target.files?.[0]
                if (file) onPickFile(file)
              }}
            />
          </label>
          <span className="text-[11px] text-slate-500">
            {raw.trim() ? raw.split(/[\n,;]+/).filter((v) => v.trim()).length : 0} numbers
          </span>
        </div>

        {error ? <p className="mt-2 text-xs text-rose-400">{error}</p> : null}

        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="btn-quiet">
            Cancel
          </button>
          <button type="button" onClick={submit} disabled={busy} className="btn-primary w-auto px-5">
            {busy ? 'Matching…' : 'Match contacts'}
          </button>
        </div>
      </div>
    </div>
  )
}
