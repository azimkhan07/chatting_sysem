import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { categoriesApi } from '@/lib/api'
import { profileApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { AccountType, ProfileCategoryKey, User } from '@/types/user'

const MAX_BIO_LENGTH = 160
const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp']

/**
 * Personal is message + follow only. The other two unlock the published
 * contact block, mirroring `AccountType::offersContact()` on the backend.
 * Professional and business are only offered for public accounts — a private
 * account is personal by definition.
 */
const ACCOUNT_TYPES = [
  {
    value: 'personal',
    label: 'Personal',
    hint: 'Follow and message only. No contact details shown.',
  },
  {
    value: 'professional',
    label: 'Professional',
    hint: 'Let visitors reach you on WhatsApp or email.',
  },
  {
    value: 'business',
    label: 'Business',
    hint: 'Same contact options, labelled as a business.',
  },
] as const

interface EditProfileModalProps {
  onClose: () => void
}

export default function EditProfileModal({ onClose }: EditProfileModalProps) {
  const sessionUser = useAuthStore((state) => state.user)
  const setUser = useAuthStore((state) => state.setUser)
  const queryClient = useQueryClient()

  const avatarInputRef = useRef<HTMLInputElement>(null)
  const coverInputRef = useRef<HTMLInputElement>(null)

  const [displayName, setDisplayName] = useState(sessionUser?.display_name ?? '')
  const [bio, setBio] = useState(sessionUser?.bio ?? '')
  const [accountType, setAccountType] = useState<AccountType>(
    sessionUser?.account_type ?? 'personal',
  )
  const [isPrivate, setIsPrivate] = useState(sessionUser?.is_private ?? false)
  const [category, setCategory] = useState<ProfileCategoryKey | null>(
    sessionUser?.category ?? null,
  )
  const [contactEmail, setContactEmail] = useState(sessionUser?.contact_email ?? '')
  const [contactPhone, setContactPhone] = useState(sessionUser?.contact_phone ?? '')
  const [showContact, setShowContact] = useState(sessionUser?.show_contact ?? false)
  const [avatar, setAvatar] = useState<{ file: File; previewUrl: string } | null>(null)
  const [cover, setCover] = useState<{ file: File; previewUrl: string } | null>(null)
  const [error, setError] = useState<string | null>(null)

  const offersContact = accountType !== 'personal'
  const trimmedEmail = contactEmail.trim()
  const trimmedPhone = contactPhone.trim()
  const hasContact = trimmedEmail.length > 0 || trimmedPhone.length > 0

  const categoriesQuery = useQuery({
    queryKey: ['categories'],
    queryFn: () => categoriesApi.list(),
    staleTime: 10 * 60_000,
  })
  const categories = categoriesQuery.data?.categories ?? []

  const avatarPreview = avatar?.previewUrl ?? sessionUser?.avatar_url
  const coverPreview = cover?.previewUrl ?? sessionUser?.cover_url

  useEffect(() => {
    const urls = [avatar, cover]
      .filter((item): item is { file: File; previewUrl: string } => item !== null)
      .map((item) => item.previewUrl)
    return () => urls.forEach((url) => URL.revokeObjectURL(url))
  }, [avatar, cover])

  const updateProfile = useMutation({
    mutationFn: (data: {
      display_name: string
      bio: string
      account_type: AccountType
      is_private: boolean
      category: ProfileCategoryKey | null
      contact_email: string | null
      contact_phone: string | null
      show_contact: boolean
    }) => profileApi.update(data),
  })
  const uploadAvatar = useMutation({
    mutationFn: (file: File) => profileApi.uploadAvatar(file),
  })
  const uploadCover = useMutation({
    mutationFn: (file: File) => profileApi.uploadCover(file),
  })

  const isSaving =
    updateProfile.isPending || uploadAvatar.isPending || uploadCover.isPending

  function pickImage(kind: 'avatar' | 'cover', list: FileList | null) {
    const file = list?.[0]
    if (!file || !ACCEPTED_MIME.includes(file.type)) return
    const preview = { file, previewUrl: URL.createObjectURL(file) }
    if (kind === 'avatar') setAvatar(preview)
    else setCover(preview)
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    setError(null)

    try {
      let next: User = (
        await updateProfile.mutateAsync({
          display_name: displayName.trim(),
          bio: bio.trim(),
          account_type: isPrivate ? 'personal' : accountType,
          is_private: isPrivate,
          // Only public professional/business accounts carry a category.
          category: isPrivate || !offersContact ? null : category,
          // Sending null rather than an empty string: the backend treats a
          // blank as "remove this", and personal accounts wipe the block.
          contact_email: offersContact && !isPrivate && trimmedEmail ? trimmedEmail : null,
          contact_phone: offersContact && !isPrivate && trimmedPhone ? trimmedPhone : null,
          show_contact: offersContact && !isPrivate && showContact,
        })
      ).user

      if (avatar) {
        next = (await uploadAvatar.mutateAsync(avatar.file)).user
      }
      if (cover) {
        next = (await uploadCover.mutateAsync(cover.file)).user
      }

      setUser(next)
      queryClient.invalidateQueries({
        queryKey: ['user', 'profile', next.username],
      })
      onClose()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Something went wrong.')
    }
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm">
      <motion.form
        initial={{ opacity: 0, y: 12, scale: 0.97 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.22, ease: 'easeOut' }}
        onSubmit={handleSubmit}
        className="max-h-[92dvh] w-full max-w-md overflow-y-auto rounded-3xl glass-card"
      >
        {/* Cover */}
        <div className="relative h-28 bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20">
          {coverPreview ? (
            <img src={coverPreview} alt="Cover preview" className="h-full w-full object-cover" />
          ) : null}
          <input
            ref={coverInputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={(event) => pickImage('cover', event.target.files)}
          />
          <button
            type="button"
            onClick={() => coverInputRef.current?.click()}
            className="btn-quiet absolute right-2 bottom-2 px-2.5 py-1 text-[11px]"
          >
            Change cover
          </button>
        </div>

        <div className="-mt-7 px-4 pb-4">
          {/* Avatar */}
          <div className="relative w-fit">
            <input
              ref={avatarInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="sr-only"
              onChange={(event) => pickImage('avatar', event.target.files)}
            />
            <button
              type="button"
              onClick={() => avatarInputRef.current?.click()}
              className="grid h-14 w-14 overflow-hidden rounded-full ring-4 ring-midnight-950"
              title="Change avatar"
            >
              {avatarPreview ? (
                <img src={avatarPreview} alt="Avatar preview" className="h-full w-full object-cover" />
              ) : (
                <span className="grid h-full w-full place-items-center bg-gradient-to-br from-brand-400 to-fuchsia-500 text-lg font-bold text-[#fff]">
                  {(displayName || '?').charAt(0).toUpperCase()}
                </span>
              )}
            </button>
          </div>

          <label className="mt-4 block text-xs font-bold text-slate-300">
            Display name
            <input
              value={displayName}
              onChange={(event) => setDisplayName(event.target.value)}
              maxLength={60}
              className="mt-1 w-full rounded-xl bg-white/5 px-3 py-2 text-sm text-white outline-none ring-1 ring-white/10 focus:ring-brand-400/60"
            />
          </label>

          <label className="mt-3 block text-xs font-bold text-slate-300">
            Bio
            <textarea
              value={bio}
              onChange={(event) => setBio(event.target.value)}
              maxLength={MAX_BIO_LENGTH}
              rows={2}
              placeholder="Tell people who you are…"
              className="mt-1 min-h-[3.25rem] w-full resize-none rounded-xl bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none ring-1 ring-white/10 focus:ring-brand-400/60"
            />
            <span className="mt-0.5 block text-right text-[10px] text-slate-500">
              {bio.length}/{MAX_BIO_LENGTH}
            </span>
          </label>

          {/* Visibility: public vs private */}
          <fieldset className="mt-4 rounded-2xl bg-white/[0.03] p-3 ring-1 ring-white/10">
            <legend className="px-1 text-xs font-bold text-slate-300">Visibility</legend>
            <div className="mt-1.5 grid grid-cols-2 gap-1.5" role="radiogroup">
              {([
                {
                  value: false,
                  label: 'Public',
                  hint: 'Everyone can see your profile and posts.',
                },
                {
                  value: true,
                  label: 'Private',
                  hint: 'Only approved followers see your content.',
                },
              ] as const).map((option) => {
                const active = isPrivate === option.value
                return (
                  <label
                    key={String(option.value)}
                    className={`flex cursor-pointer items-start gap-2.5 rounded-xl px-2.5 py-2 transition ${
                      active ? 'bg-brand-500/15 ring-1 ring-brand-400/50' : 'hover:bg-white/5'
                    }`}
                  >
                    <input
                      type="radio"
                      name="visibility"
                      checked={active}
                      onChange={() => setIsPrivate(option.value)}
                      className="mt-0.5 h-4 w-4 accent-brand-500"
                    />
                    <span className="min-w-0">
                      <span className="block text-sm font-semibold text-slate-100">
                        {option.label}
                      </span>
                      <span className="block text-[11px] leading-snug text-slate-400">
                        {option.hint}
                      </span>
                    </span>
                  </label>
                )
              })}
            </div>
            <p className="mt-2 text-[11px] leading-relaxed text-slate-400">
              {isPrivate
                ? 'Private accounts are personal. Follow requests gate your posts and message button.'
                : 'Public accounts can add a Professional or Business profile with a contact method.'}
            </p>
          </fieldset>

          {/* Account type + public contact - only for public accounts */}
          {!isPrivate ? (
            <fieldset className="mt-3 rounded-2xl bg-white/[0.03] p-3 ring-1 ring-white/10">
              <legend className="px-1 text-xs font-bold text-slate-300">Account type</legend>
              <div className="mt-1.5 grid gap-1.5" role="radiogroup">
                {ACCOUNT_TYPES.map((option) => {
                  const active = accountType === option.value
                  return (
                    <label
                      key={option.value}
                      className={`flex cursor-pointer items-start gap-2.5 rounded-xl px-2.5 py-2 transition ${
                        active ? 'bg-brand-500/15 ring-1 ring-brand-400/50' : 'hover:bg-white/5'
                      }`}
                    >
                      <input
                        type="radio"
                        name="account_type"
                        value={option.value}
                        checked={active}
                        onChange={() => setAccountType(option.value)}
                        className="mt-0.5 h-4 w-4 accent-brand-500"
                      />
                      <span className="min-w-0">
                        <span className="block text-sm font-semibold text-slate-100">
                          {option.label}
                        </span>
                        <span className="block text-[11px] leading-snug text-slate-400">
                          {option.hint}
                        </span>
                      </span>
                    </label>
                  )
                })}
              </div>

              {/* Creator category - business only */}
              {accountType === 'business' ? (
                <div className="mt-3">
                  <label className="block text-[11px] font-bold tracking-wide text-slate-400 uppercase">
                    Category
                    <select
                      value={category ?? ''}
                      onChange={(event) => setCategory((event.target.value || null) as ProfileCategoryKey | null)}
                      className="mt-1 w-full rounded-xl bg-midnight-950 px-3 py-2 text-sm text-white outline-none ring-1 ring-white/10 focus:ring-brand-400/60"
                    >
                      <option value="">Select a category…</option>
                      {categories.map((option) => (
                        <option key={option.key} value={option.key}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </label>
                  <p className="mt-1 text-[11px] leading-snug text-slate-400">
                    Shown under your username, e.g. Artist, Entertainment or Sport.
                  </p>
                </div>
              ) : null}

              {offersContact ? (
                <div className="mt-3 space-y-2.5">
                  <label className="block text-[11px] font-bold tracking-wide text-slate-400 uppercase">
                    WhatsApp number
                    <input
                      value={contactPhone}
                      onChange={(event) => setContactPhone(event.target.value)}
                      inputMode="tel"
                      placeholder="+91 98765 43210"
                      className="mt-1 w-full rounded-xl bg-white/5 px-3 py-2 text-sm text-white placeholder:text-slate-500 outline-none ring-1 ring-white/10 focus:ring-brand-400/60"
                    />
                  </label>

                  <label className="block text-[11px] font-bold tracking-wide text-slate-400 uppercase">
                    Email
                    <input
                      value={contactEmail}
                      onChange={(event) => setContactEmail(event.target.value)}
                      type="email"
                      placeholder="hello@studio.com"
                      className="mt-1 w-full rounded-xl bg-white/5 px-3 py-2 text-sm text-white placeholder:text-slate-500 outline-none ring-1 ring-white/10 focus:ring-brand-400/60"
                    />
                  </label>

                  <label
                    className={`flex items-start gap-2.5 rounded-xl px-2.5 py-2 transition ${
                      hasContact
                        ? 'cursor-pointer hover:bg-white/5'
                        : 'cursor-not-allowed opacity-50'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={showContact && hasContact}
                      disabled={!hasContact}
                      onChange={(event) => setShowContact(event.target.checked)}
                      className="mt-0.5 h-4 w-4 accent-brand-500"
                    />
                    <span className="min-w-0">
                      <span className="block text-sm font-semibold text-slate-100">
                        Show contact on my profile
                      </span>
                      <span className="block text-[11px] leading-snug text-slate-400">
                        {hasContact
                          ? 'Visitors get a Contact button and pick WhatsApp or email. Your login email and mobile are never shared.'
                          : 'Add a number or an email first.'}
                      </span>
                    </span>
                  </label>
                </div>
              ) : (
                <p className="mt-2 text-[11px] leading-relaxed text-slate-400">
                  Personal accounts show a Message button only. Switch to Professional or Business to
                  publish a contact.
                </p>
              )}
            </fieldset>
          ) : null}

          {error ? <p className="mt-2 text-xs text-rose-400">{error}</p> : null}

          <div className="mt-4 flex items-center gap-2">
            <button
              type="button"
              onClick={onClose}
              className="btn-quiet flex-1 py-2.5"
              disabled={isSaving}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSaving || displayName.trim().length < 2}
              className="btn-primary flex-1 py-2.5"
            >
              {isSaving ? <Spinner className="h-4 w-4" /> : null}
              {isSaving ? 'Saving…' : 'Save changes'}
            </button>
          </div>
        </div>
      </motion.form>
    </div>
  )
}