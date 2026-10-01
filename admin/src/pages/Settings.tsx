import {
  Alert,
  Badge,
  Button,
  Group,
  Loader,
  Paper,
  PasswordInput,
  Select,
  Stack,
  Table,
  Tabs,
  Text,
  TextInput,
} from '@mantine/core'
import {
  IconDeviceDesktop,
  IconLock,
  IconShieldCog,
  IconUsers,
} from '@tabler/icons-react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError, type AuthUser } from '../lib/api'
import { useCan } from '../lib/permissions'

interface StaffRecord extends AuthUser {
  role: string | null
  active_devices: number
  created_at?: string
}

/** One live console session. The API never returns the token itself. */
interface StaffSession {
  id: number
  staff_id: number
  username: string
  display_name: string
  role: string | null
  browser: string | null
  os: string | null
  device_type: string | null
  client: string | null
  ip_address: string | null
  is_current: boolean
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
}

interface SessionsResponse {
  sessions: StaffSession[]
  summary: {
    total: number
    staff_with_sessions: number
    device_cap: number
    unrecorded_context: number
  }
}

/** A null from the API is "not recorded", which is not the same as "unknown". */
function orDash(value: string | null | undefined): string {
  return value && value.trim() !== '' ? value : '—'
}

function roleColor(role: string | null): string {
  if (role === 'super_admin') return 'red'
  if (role === 'admin') return 'orange'
  if (role === 'support') return 'blue'
  return 'gray'
}

/** ISO string or null to something a person can read; null becomes a dash. */
function formatWhen(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const EMPTY_PW = { current_password: '', new_password: '', new_password_confirmation: '' }
const EMPTY_STAFF = {
  username: '',
  display_name: '',
  password: '',
  password_confirmation: '',
  role: 'support',
}

// Kept separate from EMPTY_STAFF so the two create forms cannot bleed into one
// another: an admin's default role is `support` and a super admin's is `admin`,
// and sharing one object meant whichever form was touched last decided the role
// offered on the other tab.
const EMPTY_ADMIN = {
  username: '',
  display_name: '',
  password: '',
  password_confirmation: '',
  role: 'admin',
}

export default function Settings() {
  const qc = useQueryClient()
  const [pw, setPw] = useState(EMPTY_PW)
  const [staffForm, setStaffForm] = useState(EMPTY_STAFF)
  const [adminForm, setAdminForm] = useState(EMPTY_ADMIN)
  const [pwError, setPwError] = useState<string | null>(null)
  const [staffError, setStaffError] = useState<string | null>(null)
  const [adminError, setAdminError] = useState<string | null>(null)
  const [pwDone, setPwDone] = useState(false)

  const can = useCan()
  const canViewStaff = can('viewStaff')
  const canManageStaff = can('manageStaff')
  const canCreateAdminStaff = can('createAdminStaff')
  const canViewSessions = can('viewStaffSessions')

  const staffQuery = useQuery({
    queryKey: ['admin', 'staff'],
    queryFn: () => adminApi.get<{ staff: StaffRecord[] }>('/admin/staff'),
    enabled: canViewStaff,
  })
  const changePw = useMutation({
    mutationFn: () =>
      adminApi.post<{ message: string }>('/admin/auth/password', {
        current_password: pw.current_password,
        new_password: pw.new_password,
        new_password_confirmation: pw.new_password_confirmation,
      }),
    onSuccess: () => {
      setPw(EMPTY_PW)
      setPwDone(true)
    },
  })

  const createStaff = useMutation({
    mutationFn: (form: typeof EMPTY_STAFF) =>
      adminApi.post<StaffRecord>('/admin/staff', form),
    onSuccess: () => {
      setStaffForm(EMPTY_STAFF)
      void qc.invalidateQueries({ queryKey: ['admin', 'staff'] })
    },
  })

  // Minting an admin is a super-admin-only action, so it is a separate mutation
  // with a separate form rather than the same endpoint called with a different
  // role. One mutation serving both tabs would mean the wrong tab's error
  // surfaced on this one.
  const createAdmin = useMutation({
    mutationFn: () => adminApi.post<StaffRecord>('/admin/staff', adminForm),
    onSuccess: () => {
      setAdminForm(EMPTY_ADMIN)
      void qc.invalidateQueries({ queryKey: ['admin', 'staff'] })
    },
  })

  // Not refetched on focus or on an interval. This is a sign-in record, and
  // polling it would make a session list that is meant to be evidence quietly
  // rewrite itself while somebody reads it. It updates when the tab is opened.
  const sessionQuery = useQuery({
    queryKey: ['admin', 'staff-sessions'],
    queryFn: () => adminApi.get<SessionsResponse>('/admin/sessions/staff'),
    enabled: canViewSessions,
    refetchOnWindowFocus: false,
    staleTime: 30_000,
  })

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Settings
      </Text>

      {/* Vertical tabs: the nav sits on the left, the clicked tab's form on the
          right. The tab list is built from capability rather than hard-coded, so
          a role is never shown a section that the API would refuse — an
          "Administrators" tab that 403s on submit is worse than no tab. Password
          is first and always present, since rotating your own credentials is the
          one thing every staff member needs, including a read-only moderator. */}
      <Tabs orientation="vertical" defaultValue="password">
        <Tabs.List>
          <Tabs.Tab value="password" leftSection={<IconLock size={16} />}>
            Password
          </Tabs.Tab>
          {canViewStaff && (
            <Tabs.Tab value="team" leftSection={<IconUsers size={16} />}>
              Team
            </Tabs.Tab>
          )}
          {canCreateAdminStaff && (
            <Tabs.Tab value="administrators" leftSection={<IconShieldCog size={16} />}>
              Administrators
            </Tabs.Tab>
          )}
          {canViewSessions && (
            <Tabs.Tab value="sessions" leftSection={<IconDeviceDesktop size={16} />}>
              Sessions
            </Tabs.Tab>
          )}
        </Tabs.List>

        <Tabs.Panel value="password">
          <Paper withBorder p="md" bg="white" maw={480}>
            <Text fw={600} mb="xs">
              Change password
            </Text>
            <Text size="xs" c="dimmed" mb="md">
              Use this to rotate your own credentials after the first login.
            </Text>
            <Stack>
              <PasswordInput
                label="Current password"
                value={pw.current_password}
                onChange={(e) => setPw((f) => ({ ...f, current_password: e.currentTarget.value }))}
              />
              <PasswordInput
                label="New password"
                value={pw.new_password}
                onChange={(e) => setPw((f) => ({ ...f, new_password: e.currentTarget.value }))}
              />
              <PasswordInput
                label="Confirm new password"
                value={pw.new_password_confirmation}
                onChange={(e) =>
                  setPw((f) => ({ ...f, new_password_confirmation: e.currentTarget.value }))
                }
              />
              {pwDone && (
                <Alert color="green" withCloseButton onClose={() => setPwDone(false)}>
                  Password updated.
                </Alert>
              )}
              {pwError && <Alert color="red">{pwError}</Alert>}
              <Group justify="flex-end">
                <Button
                  loading={changePw.isPending}
                  onClick={() =>
                    changePw.mutate(undefined, {
                      onError: (e) => {
                        setPwDone(false)
                        setPwError(e instanceof ApiError ? e.message : 'Change failed')
                      },
                    })
                  }
                >
                  Update password
                </Button>
              </Group>
            </Stack>
          </Paper>
        </Tabs.Panel>

        {canViewStaff && (
          <Tabs.Panel value="team">
            <Paper withBorder p="md" bg="white" maw={640}>
              {canManageStaff && (
                <>
                  <Text fw={600} mb="xs">
                    Create staff account
                  </Text>
                  <Text size="xs" c="dimmed" mb="md">
                    You can add support agents and moderators. Admin and super admin accounts are
                    created by a super admin.
                  </Text>

                  <Stack gap="sm" mb="md">
                    <Group grow>
                      <TextInput
                        label="Username"
                        value={staffForm.username}
                        onChange={(e) =>
                          setStaffForm((f) => ({ ...f, username: e.currentTarget.value }))
                        }
                      />
                      <TextInput
                        label="Display name"
                        value={staffForm.display_name}
                        onChange={(e) =>
                          setStaffForm((f) => ({ ...f, display_name: e.currentTarget.value }))
                        }
                      />
                    </Group>
                    <Group grow>
                      <PasswordInput
                        label="Password"
                        value={staffForm.password}
                        onChange={(e) =>
                          setStaffForm((f) => ({ ...f, password: e.currentTarget.value }))
                        }
                      />
                      <PasswordInput
                        label="Confirm password"
                        value={staffForm.password_confirmation}
                        onChange={(e) =>
                          setStaffForm((f) => ({
                            ...f,
                            password_confirmation: e.currentTarget.value,
                          }))
                        }
                      />
                    </Group>
                    <Select
                      label="Role"
                      data={['support', 'moderator']}
                      value="support"
                      disabled
                      description="Admin and super admin accounts are created from the Administrators tab."
                    />
                    {staffError && <Alert color="red">{staffError}</Alert>}
                    <Group justify="flex-end">
                      <Button
                        loading={createStaff.isPending}
                        onClick={() =>
                          createStaff.mutate(
                            { ...staffForm, role: 'support' },
                            {
                              onError: (e) =>
                                setStaffError(e instanceof ApiError ? e.message : 'Create failed'),
                            },
                          )
                        }
                      >
                        Create account
                      </Button>
                    </Group>
                  </Stack>
                </>
              )}

              {canManageStaff ? (
                staffQuery.isPending && <Loader my="md" />
              ) : (
                <Text size="xs" c="dimmed" mb="md">
                  View only — a moderator can read this list but cannot add or remove accounts.
                </Text>
              )}

              {canManageStaff && !staffQuery.isPending && (
                <Table.ScrollContainer minWidth={480}>
                  <Table highlightOnHover>
                    <Table.Thead>
                      <Table.Tr>
                        <Table.Th>Username</Table.Th>
                        <Table.Th>Display name</Table.Th>
                        <Table.Th>Role</Table.Th>
                        <Table.Th>Devices</Table.Th>
                      </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                      {(staffQuery.data?.staff ?? []).map((s) => (
                        <Table.Tr key={s.id}>
                          <Table.Td>{s.username}</Table.Td>
                          <Table.Td>{s.display_name || '—'}</Table.Td>
                          <Table.Td>{s.role ?? '—'}</Table.Td>
                          <Table.Td>{s.active_devices}</Table.Td>
                        </Table.Tr>
                      ))}
                    </Table.Tbody>
                  </Table>
                </Table.ScrollContainer>
              )}
            </Paper>
          </Tabs.Panel>
        )}

        {canCreateAdminStaff && (
          <Tabs.Panel value="administrators">
            <Paper withBorder p="md" bg="white" maw={640}>
              <Text fw={600} mb="xs">
                Create an administrator
              </Text>
              <Text size="xs" c="dimmed" mb="md">
                A super admin is the only role that can mint another admin or super admin. Keep this
                list short: every admin can change plan pricing, email delivery and payment
                gateways, which means sending mail and moving money.
              </Text>

              <Stack gap="sm">
                <Group grow>
                  <TextInput
                    label="Username"
                    value={adminForm.username}
                    onChange={(e) =>
                      setAdminForm((f) => ({ ...f, username: e.currentTarget.value }))
                    }
                  />
                  <TextInput
                    label="Display name"
                    value={adminForm.display_name}
                    onChange={(e) =>
                      setAdminForm((f) => ({ ...f, display_name: e.currentTarget.value }))
                    }
                  />
                </Group>
                <Group grow>
                  <PasswordInput
                    label="Password"
                    value={adminForm.password}
                    onChange={(e) =>
                      setAdminForm((f) => ({ ...f, password: e.currentTarget.value }))
                    }
                  />
                  <PasswordInput
                    label="Confirm password"
                    value={adminForm.password_confirmation}
                    onChange={(e) =>
                      setAdminForm((f) => ({
                        ...f,
                        password_confirmation: e.currentTarget.value,
                      }))
                    }
                  />
                </Group>
                <Select
                  label="Role"
                  data={[
                    { value: 'admin', label: 'Admin — configuration and team' },
                    { value: 'super_admin', label: 'Super admin — also creates admins' },
                  ]}
                  value={adminForm.role}
                  onChange={(v) => setAdminForm((f) => ({ ...f, role: v ?? 'admin' }))}
                  allowDeselect={false}
                />
                {adminError && <Alert color="red">{adminError}</Alert>}
                <Group justify="flex-end">
                  <Button
                    loading={createAdmin.isPending}
                    onClick={() =>
                      createAdmin.mutate(undefined, {
                        onError: (e) =>
                          setAdminError(e instanceof ApiError ? e.message : 'Create failed'),
                      })
                    }
                  >
                      Create administrator
                    </Button>
                  </Group>
                </Stack>
              </Paper>
            </Tabs.Panel>
        )}

        {canViewSessions && (
          <Tabs.Panel value="sessions">
            <Paper withBorder p="md" bg="white">
              <Text fw={600} mb="xs">
                Active sessions
              </Text>
              <Text size="xs" c="dimmed" mb="md">
                Every staff account currently signed in to this console, with the address and
                browser it signed in from. Each account is capped at{' '}
                {sessionQuery.data?.summary.device_cap ?? '—'} devices, so a further login replaces
                the oldest.
              </Text>

              {sessionQuery.isPending && <Loader my="md" />}

              {sessionQuery.isError && (
                <Alert color="red" mb="md">
                  Could not load the session list.
                </Alert>
              )}

              {sessionQuery.data && sessionQuery.data.sessions.length === 0 && (
                <Text c="dimmed">Nobody is signed in.</Text>
              )}

              {sessionQuery.data && sessionQuery.data.sessions.length > 0 && (
                <>
                  <Group gap="lg" mb="md">
                    <Text size="sm">
                      <Text span fw={700}>
                        {sessionQuery.data.summary.total}
                      </Text>{' '}
                      active session
                      {sessionQuery.data.summary.total === 1 ? '' : 's'}
                    </Text>
                    <Text size="sm">
                      across{' '}
                      <Text span fw={700}>
                        {sessionQuery.data.summary.staff_with_sessions}
                      </Text>{' '}
                      account
                      {sessionQuery.data.summary.staff_with_sessions === 1 ? '' : 's'}
                    </Text>
                  </Group>

                  {sessionQuery.data.summary.unrecorded_context > 0 && (
                    <Alert color="yellow" mb="md">
                      {sessionQuery.data.summary.unrecorded_context} session
                      {sessionQuery.data.summary.unrecorded_context === 1 ? '' : 's'} started
                      before sign-in tracking was added, so the address and browser were never
                      recorded. They are listed with a dash rather than hidden, so the count stays
                      honest.
                    </Alert>
                  )}

                  <Table.ScrollContainer minWidth={860}>
                    <Table highlightOnHover>
                      <Table.Thead>
                        <Table.Tr>
                          <Table.Th>Staff</Table.Th>
                          <Table.Th>Role</Table.Th>
                          <Table.Th>Browser</Table.Th>
                          <Table.Th>Device</Table.Th>
                          <Table.Th>App</Table.Th>
                          <Table.Th>Signed in from</Table.Th>
                          <Table.Th>Last used</Table.Th>
                          <Table.Th>Expires</Table.Th>
                        </Table.Tr>
                      </Table.Thead>
                      <Table.Tbody>
                        {sessionQuery.data.sessions.map((s) => (
                          <Table.Tr key={s.id}>
                            <Table.Td>
                              <Text size="sm" fw={s.is_current ? 700 : 400}>
                                {s.display_name || s.username}
                              </Text>
                              <Text size="xs" c="dimmed">
                                {s.username}
                                {s.is_current ? ' · this device' : ''}
                              </Text>
                            </Table.Td>
                            <Table.Td>
                              <Badge size="sm" variant="light" color={roleColor(s.role)}>
                                {s.role ?? '—'}
                              </Badge>
                            </Table.Td>
                            <Table.Td>
                              <Text size="sm">{orDash(s.browser)}</Text>
                              <Text size="xs" c="dimmed">
                                {orDash(s.os)}
                              </Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="sm" tt="capitalize">
                                {orDash(s.device_type)}
                              </Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="sm" tt="capitalize">
                                {orDash(s.client)}
                              </Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="sm" ff="monospace">
                                {orDash(s.ip_address)}
                              </Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="sm">{formatWhen(s.last_used_at ?? s.created_at)}</Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="sm" c="dimmed">
                                {formatWhen(s.expires_at)}
                              </Text>
                            </Table.Td>
                          </Table.Tr>
                        ))}
                      </Table.Tbody>
                    </Table>
                  </Table.ScrollContainer>
                </>
              )}
            </Paper>
          </Tabs.Panel>
        )}
      </Tabs>
    </Stack>
  )
}
