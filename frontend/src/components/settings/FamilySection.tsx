import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { SettingCard } from '@/components/settings/SettingCard'
import { Divider, FIELD, SubHeading } from '@/components/settings/SettingsUI'
import { familyApi } from '@/lib/api'
import type { Family, FamilyMember, FamilyRole, FamilyRoleOption } from '@/types/settings'

/**
 * Family Center.
 *
 * Three states, one component: no family, a roster, or an error. The permissions
 * on every row come from the server (`member.permissions`) rather than from
 * comparing the viewer's role against the target's here - one source of truth
 * means the buttons cannot disagree with the server about what is allowed.
 */
export function FamilySection() {
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)

  const query = useQuery({
    queryKey: ['family'],
    queryFn: familyApi.show,
  })

  // Every family mutation returns the whole family, so one invalidation
  // refreshes the roster, the counts and the viewer badge together.
  const settle = (family: Family | null) => {
    queryClient.setQueryData(['family'], { family })
    void queryClient.invalidateQueries({ queryKey: ['account'] })
  }

  const fail = (caught: unknown) =>
    setError(caught instanceof Error ? caught.message : 'That did not work. Please try again.')

  const rename = useMutation({
    mutationFn: familyApi.rename,
    onSuccess: (result) => settle(result.family),
    onError: fail,
  })
  const add = useMutation({
    mutationFn: (input: { username: string; role: FamilyRole }) =>
      familyApi.addMember(input.username, input.role),
    onSuccess: (result) => settle(result.family),
    onError: fail,
  })
  const changeRole = useMutation({
    mutationFn: (input: { id: number; role: FamilyRole }) =>
      familyApi.changeRole(input.id, input.role),
    onSuccess: (result) => settle(result.family),
    onError: fail,
  })
  const remove = useMutation({
    mutationFn: familyApi.removeMember,
    onSuccess: (result) => settle(result.family),
    onError: fail,
  })
  const leave = useMutation({
    mutationFn: familyApi.leave,
    onSuccess: () => settle(null),
    onError: fail,
  })
  const dissolve = useMutation({
    mutationFn: familyApi.dissolve,
    onSuccess: () => settle(null),
    onError: fail,
  })

  const create = useMutation({
    mutationFn: familyApi.create,
    onSuccess: (result) => settle(result.family),
    onError: fail,
  })

  const family = query.data?.family ?? null

  return (
    <SettingCard
      title="Family Center"
      description={
        family
          ? `${family.counts.members} ${
              family.counts.members === 1 ? 'member' : 'members'
            } on one shared supervision policy.`
          : 'Share one supervision policy with the people you look after.'
      }
    >
      {error ? <ErrorNote message={error} onDismiss={() => setError(null)} /> : null}

      {query.isPending ? (
        <div className="grid place-items-center py-6">
          <Spinner className="h-5 w-5" />
        </div>
      ) : null}

      {query.isError && !query.isPending ? (
        <p className="text-[13px] sm:text-sm text-rose-300">Could not load your family.</p>
      ) : null}

      {!query.isPending && !family ? (
        <CreateForm busy={create.isPending} onCreate={(name) => create.mutate(name)} />
      ) : null}

      {family ? (
        <Roster
          family={family}
          busy={rename.isPending || changeRole.isPending || remove.isPending || leave.isPending || dissolve.isPending}
          adding={add.isPending}
          onRename={(name) => rename.mutate(name)}
          onAdd={(username, role) => add.mutate({ username, role })}
          onChangeRole={(id, role) => changeRole.mutate({ id, role })}
          onRemove={(id) => remove.mutate(id)}
          onLeave={() => leave.mutate()}
          onDissolve={() => dissolve.mutate()}
        />
      ) : null}
    </SettingCard>
  )
}

function CreateForm({ busy, onCreate }: { busy: boolean; onCreate: (name: string) => void }) {
  const [name, setName] = useState('')

  return (
    <div>
      <p className="text-[13px] sm:text-sm leading-relaxed text-slate-400">
        A family shares one supervision policy. You become the owner and a guardian, and you can
        add the people you look after.
      </p>
      <form
        onSubmit={(event) => {
          event.preventDefault()
          onCreate(name.trim())
        }}
        className="mt-3 space-y-2"
      >
        <label className="block text-[11px] font-bold tracking-wider text-slate-500 uppercase">
          Family name
        </label>
        <input
          value={name}
          onChange={(event) => setName(event.target.value)}
          placeholder="The Sharma household"
          maxLength={60}
          className={FIELD}
        />
        <button
          type="submit"
          disabled={busy || name.trim().length < 2}
          className="w-full rounded-xl bg-brand-500 px-3 py-2 text-[13px] sm:px-4 sm:py-2.5 sm:text-sm font-semibold text-[#fff] transition hover:bg-brand-400 disabled:opacity-60"
        >
          {busy ? <Spinner className="mx-auto h-4 w-4" /> : 'Create family'}
        </button>
      </form>
    </div>
  )
}

function Roster({
  family,
  busy,
  adding,
  onRename,
  onAdd,
  onChangeRole,
  onRemove,
  onLeave,
  onDissolve,
}: {
  family: Family
  busy: boolean
  adding: boolean
  onRename: (name: string) => void
  onAdd: (username: string, role: FamilyRole) => void
  onChangeRole: (id: number, role: FamilyRole) => void
  onRemove: (id: number) => void
  onLeave: () => void
  onDissolve: () => void
}) {
  const [editingName, setEditingName] = useState(false)
  const [name, setName] = useState(family.name)
  const [confirming, setConfirming] = useState<'dissolve' | 'leave' | null>(null)

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        {editingName ? (
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={60}
            autoFocus
            aria-label="Family name"
            className={`${FIELD} flex-1`}
          />
        ) : (
          <h3 className="min-w-0 flex-1 truncate text-base font-bold text-slate-100">
            {family.name}
          </h3>
        )}
        {family.viewer.can_rename ? (
          editingName ? (
            <button
              type="button"
              disabled={busy || name.trim().length < 2}
              onClick={() => {
                onRename(name.trim())
                setEditingName(false)
              }}
              className="shrink-0 rounded-lg bg-brand-500 px-2.5 py-1.5 text-xs font-semibold text-[#fff] disabled:opacity-60"
            >
              Save
            </button>
          ) : (
            <button
              type="button"
              onClick={() => {
                setName(family.name)
                setEditingName(true)
              }}
              className="shrink-0 rounded-lg border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-slate-300 transition hover:text-slate-100"
            >
              Rename
            </button>
          )
        ) : null}
      </div>

      <p className="-mt-2 text-[11px] text-slate-500">
        You are {family.viewer.role_label?.toLowerCase()}
        {family.viewer.is_owner ? ' and the owner' : ''}.
        {family.viewer.can_approve_spending ? ' Paid plans for teens need your approval.' : ''}
      </p>

      <Divider>
        <SubHeading>Members</SubHeading>
      </Divider>

      <ul className="divide-y divide-white/5">
        {family.members.map((member) => (
          <MemberRow
            key={member.id}
            member={member}
            roles={family.roles}
            viewerIsOwner={family.viewer.is_owner}
            busy={busy}
            onChangeRole={(role) => onChangeRole(member.id, role)}
            onRemove={() => onRemove(member.id)}
          />
        ))}
      </ul>

      {family.viewer.can_manage_members ? (
        <AddMemberForm
          roles={family.roles}
          canAddGuardian={family.viewer.can_add_guardian}
          busy={adding}
          onAdd={onAdd}
        />
      ) : (
        <p className="text-xs text-slate-500">
          Only a guardian or adult can add people to this family.
        </p>
      )}

      <Divider>
        <SubHeading>Leave or end this family</SubHeading>
      </Divider>

      <div className="space-y-2">
        {family.viewer.can_leave ? (
          confirming === 'leave' ? (
            <Confirm
              title="Leave this family?"
              body="You will be removed from the roster. A guardian can add you back at any time."
              confirmLabel="Leave"
              onCancel={() => setConfirming(null)}
              onConfirm={() => {
                onLeave()
                setConfirming(null)
              }}
            />
          ) : (
            <button
              type="button"
              onClick={() => setConfirming('leave')}
              disabled={busy}
              className="w-full rounded-xl border border-white/15 px-3 py-2 text-[13px] sm:px-4 sm:py-2.5 sm:text-sm font-semibold text-slate-300 transition hover:border-amber-400/50 hover:text-amber-200 disabled:opacity-60"
            >
              Leave family
            </button>
          )
        ) : null}

        {family.viewer.can_dissolve ? (
          confirming === 'dissolve' ? (
            <Confirm
              title="Dissolve this family?"
              body="Everyone is removed and the household is deleted. Your own account and everything else stays exactly as it is."
              confirmLabel="Dissolve family"
              onCancel={() => setConfirming(null)}
              onConfirm={() => {
                onDissolve()
                setConfirming(null)
              }}
            />
          ) : (
            <button
              type="button"
              onClick={() => setConfirming('dissolve')}
              disabled={busy}
              className="w-full rounded-xl border border-rose-500/40 px-3 py-2 text-[13px] sm:px-4 sm:py-2.5 sm:text-sm font-semibold text-rose-300 transition hover:bg-rose-500/10 disabled:opacity-60"
            >
              Dissolve family
            </button>
          )
        ) : null}
      </div>
    </div>
  )
}

function MemberRow({
  member,
  roles,
  viewerIsOwner,
  busy,
  onChangeRole,
  onRemove,
}: {
  member: FamilyMember
  roles: FamilyRoleOption[]
  viewerIsOwner: boolean
  busy: boolean
  onChangeRole: (role: FamilyRole) => void
  onRemove: () => void
}) {
  const [picking, setPicking] = useState(false)
  const initials = member.user.display_name.slice(0, 2).toUpperCase()

  return (
    <li className="flex items-center gap-3 py-2.5">
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500/20 text-[11px] font-bold text-brand-200">
        {initials}
      </span>
      <span className="min-w-0 flex-1">
        <span className="flex items-center gap-1.5 text-[13px] sm:text-sm font-medium text-slate-100">
          <span className="truncate">
            {member.user.display_name}
            {member.is_self ? ' (you)' : ''}
          </span>
          {member.is_owner ? (
            <span className="shrink-0 rounded-full bg-white/10 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-slate-300 uppercase">
              Owner
            </span>
          ) : null}
        </span>
        <span className="mt-0.5 block truncate text-[11px] text-slate-500">
          @{member.user.username} · {member.role_label}
        </span>
      </span>

      <span className="flex shrink-0 items-center gap-1">
        {member.permissions.can_change_role ? (
          picking ? (
            <select
              autoFocus
              defaultValue={member.role}
              onChange={(event) => {
                onChangeRole(event.target.value as FamilyRole)
                setPicking(false)
              }}
              onBlur={() => setPicking(false)}
              disabled={busy}
              className="rounded-lg bg-white/5 px-1.5 py-1 text-[11px] text-slate-100 ring-1 ring-white/10 outline-none"
            >
              {roles
                // A non-owner guardian cannot hand out or revoke guardianship,
                // so the option is hidden rather than offered and rejected.
                .filter((role) => viewerIsOwner || role.value !== 'guardian')
                .map((role) => (
                  <option key={role.value} value={role.value}>
                    {role.label}
                  </option>
                ))}
            </select>
          ) : (
            <button
              type="button"
              onClick={() => setPicking(true)}
              disabled={busy}
              className="rounded-lg border border-white/10 px-2 py-1 text-[11px] font-semibold text-slate-300 transition hover:text-slate-100 disabled:opacity-60"
            >
              {member.role_label}
            </button>
          )
        ) : (
          <span className="text-[11px] text-slate-500">{member.role_label}</span>
        )}

        {member.permissions.can_remove ? (
          <button
            type="button"
            onClick={onRemove}
            disabled={busy}
            aria-label={`Remove ${member.user.display_name}`}
            className="rounded-lg border border-white/10 px-2 py-1 text-[11px] font-semibold text-slate-400 transition hover:border-rose-500/50 hover:text-rose-300 disabled:opacity-60"
          >
            Remove
          </button>
        ) : null}
      </span>
    </li>
  )
}

function AddMemberForm({
  roles,
  canAddGuardian,
  busy,
  onAdd,
}: {
  roles: FamilyRoleOption[]
  canAddGuardian: boolean
  busy: boolean
  onAdd: (username: string, role: FamilyRole) => void
}) {
  const [username, setUsername] = useState('')
  const [role, setRole] = useState<FamilyRole>('teen')

  const available = roles.filter((option) => canAddGuardian || option.value !== 'guardian')

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        onAdd(username.trim(), role)
        setUsername('')
      }}
      className="space-y-2 rounded-xl bg-white/[0.03] p-2.5 ring-1 sm:p-3 ring-white/10"
    >
      <label className="block text-[11px] font-bold tracking-wider text-slate-500 uppercase">
        Add someone
      </label>
      <input
        value={username}
        onChange={(event) => setUsername(event.target.value)}
        placeholder="Their username"
        maxLength={30}
        className={FIELD}
      />
      <div className="flex gap-2">
        <select
          value={role}
          onChange={(event) => setRole(event.target.value as FamilyRole)}
          className="flex-1 rounded-xl bg-white/5 px-3 py-2 text-[13px] ring-1 ring-white/10 outline-none sm:py-2.5 sm:text-sm text-slate-100 ring-1 ring-white/10 outline-none"
        >
          {available.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <button
          type="submit"
          disabled={busy || username.trim().length < 3}
          className="shrink-0 rounded-xl bg-brand-500 px-3 py-2 text-[13px] sm:px-4 sm:py-2.5 sm:text-sm font-semibold text-[#fff] transition hover:bg-brand-400 disabled:opacity-60"
        >
          {busy ? <Spinner className="h-4 w-4" /> : 'Add'}
        </button>
      </div>
      <p className="text-[11px] leading-relaxed text-slate-500">
        {available.find((option) => option.value === role)?.blurb}
      </p>
    </form>
  )
}

function Confirm({
  title,
  body,
  confirmLabel,
  onCancel,
  onConfirm,
}: {
  title: string
  body: string
  confirmLabel: string
  onCancel: () => void
  onConfirm: () => void
}) {
  return (
    <div className="rounded-xl bg-amber-500/[0.07] p-3.5 ring-1 ring-amber-500/30">
      <p className="text-[13px] sm:text-sm font-semibold text-amber-200">{title}</p>
      <p className="mt-1 text-xs leading-relaxed text-slate-300">{body}</p>
      <div className="mt-3 flex gap-2">
        <button
          type="button"
          onClick={onCancel}
          className="flex-1 rounded-xl px-3 py-1.5 text-[13px] sm:px-4 sm:py-2 sm:text-sm font-semibold text-slate-300 transition hover:bg-white/5"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={onConfirm}
          className="flex-1 rounded-xl bg-amber-500/90 px-3 py-1.5 text-[13px] sm:px-4 sm:py-2 sm:text-sm font-semibold text-slate-900 transition hover:bg-amber-500"
        >
          {confirmLabel}
        </button>
      </div>
    </div>
  )
}

function ErrorNote({ message, onDismiss }: { message: string; onDismiss: () => void }) {
  return (
    <div className="mb-3 flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 ring-1 ring-rose-500/30">
      <p className="min-w-0 flex-1 text-xs text-rose-200">{message}</p>
      <button
        type="button"
        onClick={onDismiss}
        aria-label="Dismiss"
        className="shrink-0 text-rose-300 transition hover:text-rose-100"
      >
        ✕
      </button>
    </div>
  )
}
