import { useAdminStore } from '../stores/session'

/**
 * One place that answers "may this role do X".
 *
 * The API is the real gate — every mutating route carries the `operations`
 * middleware and a moderator gets a 403 — but the console should not render a
 * button that is guaranteed to fail. Screens ask here instead of comparing
 * role strings, so adding a role or a capability is a one-file change.
 *
 *   support     tickets, appeals, subscriptions
 *   moderator   the everyday read screens, and writes nothing
 *   admin       support + console configuration + the team
 *   super_admin everything, the only role that can mint another admin, and the
 *               only one that can see where everyone is signing in from
 */
export type Capability =
  | 'operate'
  | 'viewConfig'
  | 'viewStaff'
  | 'viewStaffSessions'
  | 'manageStaff'
  | 'createAdminStaff'
  | 'resolveAppeals'
  | 'replyTickets'
  | 'reviewSubscriptions'
  | 'editPlans'
  | 'editEmail'
  | 'editGateways'
  | 'resolveReports'

const CAPABILITIES: Record<Capability, string[]> = {
  // Mirrors StaffUser::operatorRoles() on the API: a moderator is not one.
  operate: ['support', 'admin', 'super_admin'],
  // Reading the configuration screens at all. Separate from the edit*
  // capabilities because the API gates the reads on `admin` too: these screens
  // name the SMTP host, port and username and the gateway merchant id and
  // endpoint, so a role that cannot change them has no reason to see them.
  // Keeping this distinct from editEmail/editGateways is what stops the console
  // rendering a screen that is guaranteed to 403.
  viewConfig: ['admin', 'super_admin'],
  // The team roster. Mirrors the `admin` middleware on the API's staff routes,
  // which is admin and super_admin only. A moderator does not get to read the
  // list of who the admins are, and a support agent does not either.
  viewStaff: ['admin', 'super_admin'],
  // The sign-in inventory: which account, from which address, in which browser.
  // Super admin only, and narrower than viewStaff on purpose. The roster answers
  // "how many devices does this person have"; this answers "where are they
  // signing in from", which is a map of console access rather than a view of it.
  // The API enforces the same rule on `super_admin` middleware, so this is the
  // console declining to render a screen that would 403.
  viewStaffSessions: ['super_admin'],
  manageStaff: ['admin', 'super_admin'],
  createAdminStaff: ['super_admin'],
  resolveAppeals: ['support', 'super_admin'],
  replyTickets: ['support', 'admin', 'super_admin'],
  reviewSubscriptions: ['support', 'admin', 'super_admin'],
  editPlans: ['admin', 'super_admin'],
  editEmail: ['admin', 'super_admin'],
  editGateways: ['admin', 'super_admin'],
  resolveReports: ['support', 'admin', 'super_admin'],
}

export function can(role: string | null | undefined, capability: Capability): boolean {
  if (!role) return false
  return CAPABILITIES[capability].includes(role)
}

/** True when the signed-in role may see write controls at all. */
export function useCanOperate(): boolean {
  return can(useAdminStore((s) => s.admin?.role), 'operate')
}

export function useCan(): (capability: Capability) => boolean {
  const role = useAdminStore((s) => s.admin?.role)
  return (capability) => can(role, capability)
}
