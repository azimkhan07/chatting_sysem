export interface PlaceholderPageProps {
  title: string
  caption: string
  rows?: number
}

export default function PlaceholderPage({
  title,
  caption,
  rows = 10,
}: PlaceholderPageProps) {
  return (
    <div className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-extrabold tracking-tight text-white">
          {title}
        </h1>
        <p className="text-sm text-slate-400">{caption}</p>
      </header>

      <section className="glass-card p-5">
        <div className="flex items-center gap-3">
          <span className="grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 text-brand-200">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              className="h-5 w-5"
              aria-hidden="true"
            >
              <path
                d="M4 6.5h16M4 12h16M4 17.5h10"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
              />
            </svg>
          </span>
          <div>
            <p className="font-semibold text-slate-200">
              Coming in the next phase
            </p>
            <p className="text-xs text-slate-500">
              This section opens up as the product grows.
            </p>
          </div>
        </div>
      </section>

      <div className="grid gap-3 sm:grid-cols-2">
        {Array.from({ length: rows }, (_, index) => (
          <div
            key={index}
            className="glass-card flex items-center gap-3 p-4"
          >
            <span
              className="h-9 w-9 shrink-0 rounded-xl bg-gradient-to-br from-brand-500/25 to-fuchsia-500/25"
              aria-hidden="true"
            />
            <div className="flex-1 space-y-2">
              <div className="h-2 w-3/4 rounded-full bg-slate-700/60" />
              <div className="h-2 w-1/2 rounded-full bg-slate-800" />
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}