import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AnimatePresence, motion } from 'framer-motion'
import type { ReactNode } from 'react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/AuthLayout'
import PostCard from '@/components/PostCard'
import { BookmarkIcon, FolderIcon } from '@/components/icons'
import { savedApi } from '@/lib/api'
import { path } from '@/lib/paths'
import type { SavedCollection, SavedItem } from '@/types/archive'
import type { Post } from '@/types/post'
import type { Story } from '@/types/story'

export default function SavedPage() {
  const navigate = useNavigate()
  const [openedCollection, setOpenedCollection] = useState<number | null>(null)
  const [openedPost, setOpenedPost] = useState<Post | null>(null)
  const [createOpen, setCreateOpen] = useState(false)

  const overview = useQuery({
    queryKey: ['saved', 'overview'],
    queryFn: () => savedApi.overview(),
  })

  const collection = useQuery({
    queryKey: ['saved', 'collection', openedCollection],
    queryFn: () => savedApi.collectionItems(openedCollection!),
    enabled: openedCollection !== null,
  })

  return (
    <div className="mx-auto w-full max-w-4xl pb-28 pt-2">
      <header className="mb-4 flex items-center gap-3">
        <button
          type="button"
          onClick={() => navigate(path('settings'))}
          className="grid h-9 w-9 place-items-center rounded-xl bg-white/5 text-slate-300 transition hover:bg-white/10"
          aria-label="Back to settings"
        >
          ←
        </button>
        <div className="min-w-0">
          <h1 className="flex items-center gap-2 text-xl font-extrabold tracking-tight text-white">
            <BookmarkIcon className="h-5 w-5 text-brand-400" />
            Saved
          </h1>
          <p className="text-xs text-slate-500">Posts and stories you kept.</p>
        </div>
      </header>

      {openedCollection === null ? (
        <SavedGrid
          items={overview.data?.items ?? null}
          isPending={overview.isPending}
          isError={overview.isError}
          collections={overview.data?.collections ?? []}
          onOpenCollection={(id) => setOpenedCollection(id)}
          onOpenPost={(post) => setOpenedPost(post)}
          onCreateClick={() => setCreateOpen(true)}
        />
      ) : (
        <CollectionView
          collectionId={openedCollection}
          items={collection.data?.items ?? null}
          isPending={collection.isPending}
          isError={collection.isError}
          onOpenPost={(post) => setOpenedPost(post)}
          onBack={() => setOpenedCollection(null)}
        />
      )}

      {createOpen ? (
        <CreateCollectionModal onClose={() => setCreateOpen(false)} />
      ) : null}

      <AnimatePresence>
        {openedPost ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/85 p-4 backdrop-blur-sm"
          >
            <div className="w-full max-w-md">
              <PostCard post={openedPost} cacheKey={['saved', 'overview']} />
            </div>
            <button
              type="button"
              onClick={() => setOpenedPost(null)}
              className="fixed top-4 right-4 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/10 text-lg text-[#fff] backdrop-blur transition hover:bg-white/20"
              aria-label="Close"
            >
              ✕
            </button>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  )
}

function SavedGrid({
  items,
  isPending,
  isError,
  collections,
  onOpenCollection,
  onOpenPost,
  onCreateClick,
}: {
  items: SavedItem[] | null
  isPending: boolean
  isError: boolean
  collections: SavedCollection[]
  onOpenCollection: (id: number) => void
  onOpenPost: (post: Post) => void
  onCreateClick: () => void
}) {
  return (
    <div className="space-y-6">
      <section className="space-y-2">
        <div className="flex items-center justify-between px-1">
          <h2 className="text-sm font-bold text-slate-300">Collections</h2>
          <button
            type="button"
            onClick={onCreateClick}
            className="rounded-lg bg-white/5 px-3 py-1.5 text-xs font-semibold text-brand-300 transition hover:bg-white/10"
          >
            New collection
          </button>
        </div>

        {collections.length === 0 ? (
          <div className="rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-8 text-center">
            <p className="text-sm text-slate-400">
              Save a post into a folder to start organising.
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
            {collections.map((collection) => (
              <button
                key={collection.id}
                type="button"
                onClick={() => onOpenCollection(collection.id)}
                className="group aspect-square overflow-hidden rounded-xl bg-white/[0.03] ring-1 ring-white/10 transition hover:ring-brand-400/40"
              >
                <div className="flex h-full w-full flex-col items-center justify-center gap-2 p-3">
                  <FolderIcon className="h-8 w-8 text-slate-500 transition group-hover:text-brand-300" />
                  <span className="w-full truncate text-center text-[11px] font-semibold text-slate-200">
                    {collection.name}
                  </span>
                  <span className="text-[10px] text-slate-500">{collection.item_count}</span>
                </div>
              </button>
            ))}
          </div>
        )}
      </section>

      <section className="space-y-1">
        <h2 className="px-1 text-sm font-bold text-slate-300">All saved</h2>

        {isPending ? (
          <div className="grid place-items-center py-12">
            <Spinner className="h-6 w-6" />
          </div>
        ) : isError || items === null ? (
          <div className="rounded-3xl border border-dashed border-white/10 px-6 py-14 text-center text-sm text-slate-400">
            Could not load your saved items.
          </div>
        ) : items.length === 0 ? (
          <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
            <p className="text-base font-semibold text-slate-200">Nothing saved yet</p>
            <p className="mt-1 text-sm text-slate-400">
              Tap the bookmark on any post or story to keep it here.
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-3 gap-1.5">
            {items.map((item) =>
              item.saveable_type === 'post' ? (
                <SavedTile key={`post-${item.id}`} onClick={() => onOpenPost(item.saveable as Post)}>
                  <SavedMedia post={item.saveable as Post} />
                </SavedTile>
              ) : item.saveable_type === 'story' ? (
                <div
                  key={`story-${item.id}`}
                  className="aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10"
                >
                  <StoryThumb story={item.saveable as Story} />
                </div>
              ) : null,
            )}
          </div>
        )}
      </section>
    </div>
  )
}

function CollectionView({
  items,
  isPending,
  isError,
  onOpenPost,
  onBack,
}: {
  collectionId: number
  items: SavedItem[] | null
  isPending: boolean
  isError: boolean
  onOpenPost: (post: Post) => void
  onBack: () => void
}) {
  return (
    <div className="space-y-1">
      <button
        type="button"
        onClick={onBack}
        className="mb-2 flex items-center gap-1 rounded-lg text-[13px] font-semibold text-slate-400 transition hover:text-slate-200"
      >
        ← All saved
      </button>

      {isPending ? (
        <div className="grid place-items-center py-12">
          <Spinner className="h-6 w-6" />
        </div>
      ) : isError || items === null ? (
        <div className="rounded-3xl border border-dashed border-white/10 px-6 py-14 text-center text-sm text-slate-400">
          Could not load this collection.
        </div>
      ) : items.length === 0 ? (
        <div className="grid place-items-center rounded-3xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-14 text-center">
          <p className="text-base font-semibold text-slate-200">This collection is empty</p>
          <p className="mt-1 text-sm text-slate-400">
            Save something into it to see it here.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-3 gap-1.5">
          {items.map((item) =>
            item.saveable_type === 'post' ? (
              <SavedTile key={`post-${item.id}`} onClick={() => onOpenPost(item.saveable as Post)}>
                <SavedMedia post={item.saveable as Post} />
              </SavedTile>
            ) : item.saveable_type === 'story' ? (
              <div
                key={`story-${item.id}`}
                className="aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10"
              >
                <StoryThumb story={item.saveable as Story} />
              </div>
            ) : null,
          )}
        </div>
      )}
    </div>
  )
}

function SavedTile({ children, onClick }: { children: ReactNode; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="group relative aspect-square overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10"
      aria-label="Open"
    >
      {children}
    </button>
  )
}

function SavedMedia({ post }: { post: Post }) {
  const image = post.media.find((m) => m.type === 'image')
  const video = post.media.find((m) => m.type === 'video')

  if (image) {
    return (
      <img
        src={image.url}
        alt=""
        loading="lazy"
        className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
      />
    )
  }
  if (video) {
    return (
      <video
        src={video.url}
        muted
        playsInline
        preload="metadata"
        className="h-full w-full object-cover"
      />
    )
  }
  return (
    <div className="flex h-full w-full items-end bg-gradient-to-br from-brand-500/20 to-fuchsia-500/20 p-2">
      <p className="line-clamp-2 text-[11px] leading-tight text-slate-300">{post.body}</p>
    </div>
  )
}

function StoryThumb({ story }: { story: Story }) {
  return story.type === 'image' ? (
    <img
      src={story.url}
      alt=""
      loading="lazy"
      className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
    />
  ) : (
    <video
      src={story.url}
      muted
      playsInline
      preload="metadata"
      className="h-full w-full object-cover"
    />
  )
}

function CreateCollectionModal({ onClose }: { onClose: () => void }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')

  const create = useMutation({
    mutationFn: (value: string) => savedApi.createCollection(value),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['saved', 'overview'] })
      onClose()
    },
  })

  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      className="fixed inset-0 z-50 grid place-items-center bg-black/85 p-4 backdrop-blur-sm"
    >
      <div className="w-full max-w-sm rounded-3xl border border-white/10 bg-slate-900 p-5">
        <h2 className="text-lg font-extrabold text-white">New collection</h2>
        <p className="mt-1 text-sm text-slate-400">
          A folder to keep posts and stories together.
        </p>

        <form
          onSubmit={(event) => {
            event.preventDefault()
            const value = name.trim()
            if (value) create.mutate(value)
          }}
          className="mt-4 space-y-3"
        >
          <input
            autoFocus
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Collection name"
            maxLength={60}
            className="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-brand-400/60 focus:outline-none"
          />
          {create.isError ? (
            <p className="text-xs text-rose-400">Could not create the collection.</p>
          ) : null}
          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              className="rounded-xl bg-white/5 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!name.trim() || create.isPending}
              className="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-[#fff] transition hover:bg-brand-400 disabled:opacity-50"
            >
              {create.isPending ? 'Creating…' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </motion.div>
  )
}