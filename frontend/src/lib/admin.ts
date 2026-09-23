const ADMIN_STATUS_STYLES: Record<string, string> = {
  pending: 'border-amber-500/30 bg-amber-500/15 text-amber-300',
  active: 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300',
  expired: 'border-slate-500/30 bg-slate-500/15 text-slate-300',
  cancelled: 'border-rose-500/30 bg-rose-500/15 text-rose-300',
  refunded: 'border-sky-500/30 bg-sky-500/15 text-sky-300',
}

export function adminStatusStyle(status: string): string {
  return ADMIN_STATUS_STYLES[status] ?? ADMIN_STATUS_STYLES.expired
}

export function formatPaisa(paisa: number): string {
  const rupees = paisa / 100
  return `₹${rupees.toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

export function displayName(name: string | null | undefined): string {
  return name?.trim() || 'Unnamed user'
}

export function initialsOf(name: string | null | undefined): string {
  const clean = displayName(name)
  return clean
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join('')
}