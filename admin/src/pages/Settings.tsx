import {
  Alert,
  Button,
  Group,
  Loader,
  Paper,
  PasswordInput,
  Stack,
  Table,
  Text,
  TextInput,
} from '@mantine/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError, type AuthUser } from '../lib/api'
import { useAdminStore } from '../stores/session'

interface StaffRecord extends AuthUser {
  role: string | null
  active_devices: number
  created_at?: string
}

const EMPTY_PW = { current_password: '', new_password: '', new_password_confirmation: '' }
const EMPTY_STAFF = {
  username: '',
  display_name: '',
  password: '',
  password_confirmation: '',
  role: 'support',
}

export default function Settings() {
  const qc = useQueryClient()
  const [pw, setPw] = useState(EMPTY_PW)
  const [staffForm, setStaffForm] = useState(EMPTY_STAFF)
  const [pwError, setPwError] = useState<string | null>(null)
  const [staffError, setStaffError] = useState<string | null>(null)
  const [pwDone, setPwDone] = useState(false)

  const admin = useAdminStore((s) => s.admin)
  const isAdmin = admin?.role === 'admin' || admin?.role === 'super_admin'

  const staffQuery = useQuery({
    queryKey: ['admin', 'staff'],
    queryFn: () => adminApi.get<{ staff: StaffRecord[] }>('/admin/staff'),
    enabled: isAdmin,
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
    mutationFn: () =>
      adminApi.post<StaffRecord>('/admin/staff', staffForm),
    onSuccess: () => {
      setStaffForm(EMPTY_STAFF)
      void qc.invalidateQueries({ queryKey: ['admin', 'staff'] })
    },
  })

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Settings
      </Text>

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
            onChange={(e) => setPw((f) => ({ ...f, new_password_confirmation: e.currentTarget.value }))}
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

      {isAdmin && (
        <Paper withBorder p="md" bg="white" maw={640}>
          <Text fw={600} mb="xs">
            Create support account
          </Text>
          <Text size="xs" c="dimmed" mb="md">
            New support logins are created with the support role automatically.
            Support agents change their own password here after first login.
          </Text>

          <Stack gap="sm" mb="md">
            <Group grow>
              <TextInput
                label="Username"
                value={staffForm.username}
                onChange={(e) => setStaffForm((f) => ({ ...f, username: e.currentTarget.value }))}
              />
              <TextInput
                label="Display name"
                value={staffForm.display_name}
                onChange={(e) => setStaffForm((f) => ({ ...f, display_name: e.currentTarget.value }))}
              />
            </Group>
            <Group grow>
              <PasswordInput
                label="Password"
                value={staffForm.password}
                onChange={(e) => setStaffForm((f) => ({ ...f, password: e.currentTarget.value }))}
              />
              <PasswordInput
                label="Confirm password"
                value={staffForm.password_confirmation}
                onChange={(e) => setStaffForm((f) => ({ ...f, password_confirmation: e.currentTarget.value }))}
              />
            </Group>
            {staffError && <Alert color="red">{staffError}</Alert>}
            <Group justify="flex-end">
              <Button
                loading={createStaff.isPending}
                onClick={() =>
                  createStaff.mutate(undefined, {
                    onError: (e) =>
                      setStaffError(e instanceof ApiError ? e.message : 'Create failed'),
                  })
                }
              >
                Create support account
              </Button>
            </Group>
          </Stack>

          {staffQuery.isPending && <Loader my="md" />}
          {!staffQuery.isPending && (
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
      )}
    </Stack>
  )
}