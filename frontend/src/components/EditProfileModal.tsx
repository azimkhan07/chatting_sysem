import { useMutation, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { profileApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import type { User } from '@/types/user'

const MAX_BIO_LENGTH = 160
const ACCEPTED_MIME = ['image/jpeg', 'image/png', 'image/webp']

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
  const [avatar, setAvatar] = useState<{ file: File; previewUrl: string } | null>(null)
  const [cover, setCover] = useState<{ file: File; previewUrl: string } | null>(null)
  const [error, setError] = useState<string | null>(null)

  const avatarPreview = avatar?.previewUrl ?? sessionUser?.avatar_url
  const coverPreview = cover?.previewUrl ?? sessionUser?.cover_url

  useEffect(() => {
    const urls = [avatar, cover]
      .filter((item): item is { file: File; previewUrl: string } => item !== null)
      .map((item) => item.previewUrl)
    return () => urls.forEach((url) => URL.revokeObjectURL(url))
  }, [avatar, cover])

  const updateProfile = useMutation({
    mutationFn: (data: { display_name: string; bio: string }) => profileApi.update(data),
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
        className="w-full max-w-md rounded-3xl glass-card overflow-hidden"
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