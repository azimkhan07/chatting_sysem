import type { SVGProps } from 'react'

type IconProps = SVGProps<SVGSVGElement>

const base = {
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.8,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  className: 'h-5 w-5',
} as const

export function HomeIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M3 11.5 12 4l9 7.5" />
      <path d="M5.5 10v9.5h13V10" />
    </svg>
  )
}

export function ChatIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M21 12a8 8 0 0 1-8 8H4.5L7 17.5A8 8 0 1 1 21 12Z" />
    </svg>
  )
}

export function CompassIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="12" cy="12" r="9" />
      <path d="m15.5 8.5-2 5-5 2 2-5 5-2Z" />
    </svg>
  )
}

export function BellIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M6 9a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9Z" />
      <path d="M10 18a2 2 0 0 0 4 0" />
    </svg>
  )
}

export function SettingsIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="12" cy="12" r="3" />
      <path d="M12 3v2.5M12 18.5V21M21 12h-2.5M5.5 12H3M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8M18.4 18.4l-1.8-1.8M7.4 7.4 5.6 5.6" />
    </svg>
  )
}

export function LogoutIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4" />
      <path d="m15 8 4 4-4 4M9 12h10" />
    </svg>
  )
}

export function SparkleIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8" />
    </svg>
  )
}

export function ShieldCheckIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M12 3l7 3v5c0 4.4-2.9 7.6-7 9-4.1-1.4-7-4.6-7-9V6z" />
      <path d="m9 12 2 2 4-4" />
    </svg>
  )
}

export function UserIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="12" cy="8" r="3.5" />
      <path d="M5 19a7 7 0 0 1 14 0" />
    </svg>
  )
}

export function HeartIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M12 20.5C7.5 17 3.5 13.6 3.5 9.3 3.5 6.9 5.4 5 7.8 5c1.7 0 3.2.9 4.2 2.3C13 5.9 14.5 5 16.2 5c2.4 0 4.3 1.9 4.3 4.3 0 4.3-4 7.7-8.5 11.2Z" />
    </svg>
  )
}

export function MessageIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M21 12a8 8 0 0 1-8 8H4.5L7 17.5A8 8 0 1 1 21 12Z" />
      <path d="M8.5 12h7M8.5 8.5h7" />
    </svg>
  )
}

export function ShareIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="18" cy="5.5" r="2.5" />
      <circle cx="6" cy="12" r="2.5" />
      <circle cx="18" cy="18.5" r="2.5" />
      <path d="m8.2 11 7.6-4.2M8.2 13l7.6 4.2" />
    </svg>
  )
}

export function SendIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M4 12 20 4l-4.5 16-3.5-7-7-1Z" />
      <path d="m12 13 8-9" />
    </svg>
  )
}

export function ArrowLeftIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M19 12H5M11 6l-6 6 6 6" />
    </svg>
  )
}

export function ImageIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <rect x="3" y="4" width="18" height="16" rx="2" />
      <circle cx="9" cy="9.5" r="1.5" />
      <path d="m5 18 5-5 3 3 3-3 3 3" />
    </svg>
  )
}

export function PlusIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M12 5v14M5 12h14" />
    </svg>
  )
}

export function UsersIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="9" cy="8" r="3" />
      <path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
      <path d="M16 5.5a3 3 0 0 1 0 5.8M17 19a5.5 5.5 0 0 0-3-4.9" />
    </svg>
  )
}

export function XIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M6 6l12 12M18 6 6 18" />
    </svg>
  )
}

export function LinkIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M10 14a5 5 0 0 0 7.07 0l2.12-2.12a5 5 0 0 0-7.07-7.07L10.95 6" />
      <path d="M14 10a5 5 0 0 0-7.07 0L4.8 12.12a5 5 0 0 0 7.07 7.07l1.17-1.17" />
    </svg>
  )
}

export function CopyIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <rect x="9" y="9" width="11" height="11" rx="2" />
      <path d="M5 15V5a2 2 0 0 1 2-2h10" />
    </svg>
  )
}

export function CheckIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="m4.5 12.5 5 5 10-11" />
    </svg>
  )
}

export function CrownIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M4 17h16M4.5 7.5l3.6 3L12 5l3.9 5.5 3.6-3-1.4 7.5H5.9z" />
    </svg>
  )
}

export function LockIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <rect x="5" y="10.5" width="14" height="9.5" rx="2" />
      <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
    </svg>
  )
}

export function PencilIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17z" />
      <path d="m14.5 6.5 3 3" />
    </svg>
  )
}

export function BrushIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M15.5 4.5 19 8 10 17H6.5v-3.5z" />
      <path d="M13.5 6.5 16.5 9.5" />
    </svg>
  )
}

export function SmileIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="12" cy="12" r="8.5" />
      <path d="M9 14.5a4 4 0 0 0 6 0" />
      <path d="M9 9.5h.01M15 9.5h.01" />
    </svg>
  )
}

export function ImageStackIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <rect x="3.5" y="6" width="14" height="12" rx="2" />
      <path d="M7 3.5h12a1.5 1.5 0 0 1 1.5 1.5v10" />
    </svg>
  )
}

// --- Settings categories -----------------------------------------------------
// One icon per settings rail entry, all on the same 24px grid and the same
// 1.8 stroke as the primary nav, so the two rails read as one system.

/** Appearance - a half-filled circle, the classic light/dark glyph. */
export function AppearanceIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="12" cy="12" r="8.5" />
      <path d="M12 3.5a8.5 8.5 0 0 0 0 17Z" fill="currentColor" stroke="none" />
    </svg>
  )
}

/** Account Center - a person inside a rounded card. */
export function AccountIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <rect x="3" y="4.5" width="18" height="15" rx="3" />
      <circle cx="12" cy="10.5" r="2.4" />
      <path d="M7.5 16.5a4.5 4.5 0 0 1 9 0" />
    </svg>
  )
}

/** Family Center - two people, so the household reads as more than one. */
export function FamilyIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <circle cx="9" cy="9" r="3" />
      <path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
      <path d="M15.5 6.5a3 3 0 0 1 0 5.5" />
      <path d="M16.5 14.5a5.5 5.5 0 0 1 4 4.5" />
    </svg>
  )
}

/** Privacy - a hand over an eye. */
export function PrivacyIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M2.5 12S6 6 12 6s9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
      <path d="M4 19.5 20 4.5" />
    </svg>
  )
}

/** Security - a shield with a check. */
export function SecurityIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M12 3.2 19 6v5.5c0 4-2.8 7.6-7 9.3-4.2-1.7-7-5.3-7-9.3V6Z" />
      <path d="m9 12 2.2 2.2L15.5 10" />
    </svg>
  )
}

/** Help - a speech bubble with a question mark. */
export function HelpIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M20.5 12.5c0 3.9-3.8 7-8.5 7-1 0-2-.15-2.9-.43L4 20.5l1.4-3.7A6.7 6.7 0 0 1 3.5 12.5c0-3.9 3.8-7 8.5-7s8.5 3.1 8.5 7Z" />
      <path d="M10.2 10.2a1.9 1.9 0 0 1 3.7.6c0 1.3-1.9 1.9-1.9 3" />
      <path d="M12 16.6h.01" />
    </svg>
  )
}

/** Right-pointing chevron, for "this row goes somewhere". */
export function ChevronRightIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="m9.5 5.5 6.5 6.5-6.5 6.5" />
    </svg>
  )
}

/** Bookmark - a ribbon, the classic save-to-collection glyph. */
export function BookmarkIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M6.5 4h11a1 1 0 0 1 1 1v15l-6.5-4-6.5 4V5a1 1 0 0 1 1-1Z" />
    </svg>
  )
}

/** Archive - a stack of records, the walled-off storage glyph. */
export function ArchiveIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M4 8.5h16v9A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5Z" />
      <path d="M3.5 4h17a1.5 1.5 0 0 1 0 3h-17a1.5 1.5 0 0 1 0-3Z" />
      <path d="M9.5 12h5" />
    </svg>
  )
}

/** Folder - a collection on the saved grid. */
export function FolderIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M3.5 6A1.5 1.5 0 0 1 5 4.5h4.4l2 2.5H19A1.5 1.5 0 0 1 20.5 8.5v9A1.5 1.5 0 0 1 19 19H5a1.5 1.5 0 0 1-1.5-1.5Z" />
    </svg>
  )
}

/** Phone handset, for starting/joining an audio call. */
export function PhoneIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M5.6 4h3.1l1.6 3.6-2 1.6a12.5 12.5 0 0 0 5.6 5.6l1.6-2L19 14.4v3.1c0 1.1-.9 2-2 1.9A15.3 15.3 0 0 1 3.7 6c-.1-1.1.8-2 1.9-2Z" />
    </svg>
  )
}

/** Video camera, for starting/joining a video call. */
export function VideoIcon(props: IconProps) {
  return (
    <svg {...base} {...props} aria-hidden="true">
      <path d="M6 5.5h8a2.5 2.5 0 0 1 2.5 2.5v8A2.5 2.5 0 0 1 14 18.5H6A2.5 2.5 0 0 1 3.5 16V8A2.5 2.5 0 0 1 6 5.5Z" />
      <path d="m15.5 10 4-2.5v9l-4-2.5" />
    </svg>
  )
}
