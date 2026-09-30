import {
  Alert,
  Badge,
  Button,
  Group,
  Loader,
  Modal,
  Paper,
  Select,
  Stack,
  Table,
  Text,
  Textarea,
} from '@mantine/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError } from '../lib/api'

interface ReplyPayload {
  reply: string
  email?: boolean
}

interface Appeal {
  id: number
  status: string
  message: string
  resolution: string | null
  created_at: string
  user: { id: number; username: string; display_name: string; status: string }
  handler: { id: number; username: string; display_name: string } | null
}

interface Agent {
  id: number
  username: string
  display_name: string
  role: string
  status: string
  created_at: string
}

export default function Support() {
  const qc = useQueryClient()
  const [replyId, setReplyId] = useState<number | null>(null)
  const [replyText, setReplyText] = useState('')
  const [alsoEmail, setAlsoEmail] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [appealFilter, setAppealFilter] = useState('pending')
  const [resolveModal, setResolveModal] = useState<Appeal | null>(null)
  const [resolution, setResolution] = useState('')
  const [appealError, setAppealError] = useState<string | null>(null)

  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'support-tickets'],
    queryFn: () =>
      adminApi.get<{
        tickets: Array<{
          id: number
          user_name: string
          username: string
          subject: string
          message: string
          status: string
          created_at: string
        }>
      }>('/admin/support/tickets'),
  })

  const { data: agentsData } = useQuery({
    queryKey: ['admin', 'support-agents'],
    queryFn: () =>
      adminApi.get<{ agents: Agent[] }>('/admin/support/agents'),
  })

  const { data: appealsData, isLoading: appealsLoading } = useQuery({
    queryKey: ['admin', 'appeals', appealFilter],
    queryFn: () =>
      adminApi.get<{ appeals: Appeal[] }>(
        `/admin/appeals?status=${appealFilter}`,
      ),
  })

  const send = useMutation({
    mutationFn: (payload: ReplyPayload) =>
      adminApi.post<{ ok: boolean }>(`/admin/support/tickets/${replyId}/reply`, payload),
    onSuccess: () => {
      setReplyId(null)
      setReplyText('')
      setAlsoEmail(false)
      void qc.invalidateQueries({ queryKey: ['admin', 'support-tickets'] })
    },
  })

  const resolve = useMutation({
    mutationFn: (payload: { action: 'approve' | 'reject'; resolution: string }) =>
      adminApi.post<{ appeal: { id: number; status: string } }>(
        `/admin/appeals/${resolveModal?.id}/resolve`,
        payload,
      ),
    onSuccess: () => {
      setResolveModal(null)
      setResolution('')
      setAppealError(null)
      qc.invalidateQueries({ queryKey: ['admin', 'appeals'] })
      qc.invalidateQueries({ queryKey: ['admin', 'support-agents'] })
    },
  })

  function runResolve(action: 'approve' | 'reject') {
    if (!resolveModal) return
    resolve.mutate(
      { action, resolution },
      {
        onError: (e) =>
          setAppealError(e instanceof ApiError ? e.message : 'Handle failed'),
      },
    )
  }

  const tickets = data?.tickets ?? []
  const agents = agentsData?.agents ?? []
  const appeals = appealsData?.appeals ?? []

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Support
      </Text>

      <Paper withBorder p="md">
        <Text fw={600} mb="md">
          Support team ({agents.length})
        </Text>
        {agents.length === 0 ? (
          <Text size="sm" c="dimmed">
            No support accounts yet.
          </Text>
        ) : (
          <Table.ScrollContainer minWidth={560}>
            <Table highlightOnHover verticalSpacing="sm">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Account</Table.Th>
                  <Table.Th>Role</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th>Joined</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {agents.map((a) => (
                  <Table.Tr key={a.id}>
                    <Table.Td>
                      <Text fw={600}>{a.username}</Text>
                      <Text size="xs" c="dimmed">
                        {a.display_name}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Badge variant="light">{a.role}</Badge>
                    </Table.Td>
                    <Table.Td>
                      <Badge color={a.status === 'active' ? 'green' : 'gray'}>
                        {a.status}
                      </Badge>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm" c="dimmed">
                        {new Date(a.created_at).toLocaleDateString()}
                      </Text>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        )}
      </Paper>

      <Paper withBorder p="md">
        <Group justify="space-between" mb="sm">
          <Text fw={600}>Suspension appeals ({appeals.length})</Text>
          <Select
            size="xs"
            w={160}
            allowDeselect={false}
            value={appealFilter}
            onChange={(v) => setAppealFilter(v ?? 'pending')}
            data={[
              { value: 'pending', label: 'Pending' },
              { value: 'approved', label: 'Approved' },
              { value: 'rejected', label: 'Rejected' },
              { value: 'all', label: 'All' },
            ]}
          />
        </Group>
        {appealsLoading && <Loader my="lg" mx="auto" />}
        {!appealsLoading && appeals.length === 0 && (
          <Text size="sm" c="dimmed">
            No {appealFilter !== 'all' ? appealFilter : ''} appeals.
          </Text>
        )}
        {appeals.length > 0 && (
          <Table.ScrollContainer minWidth={700}>
            <Table highlightOnHover verticalSpacing="sm">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>User</Table.Th>
                  <Table.Th>Message</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th>Filed</Table.Th>
                  <Table.Th />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {appeals.map((a) => (
                  <Table.Tr key={a.id}>
                    <Table.Td>
                      <Text fw={600}>{a.user.username}</Text>
                      <Text size="xs" c="dimmed">
                        {a.user.status}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm" lineClamp={2}>
                        {a.message}
                      </Text>
                      {a.resolution && (
                        <Text size="xs" c="dimmed" lineClamp={1}>
                          Resolution: {a.resolution}
                        </Text>
                      )}
                    </Table.Td>
                    <Table.Td>
                      <Badge
                        color={
                          a.status === 'pending'
                            ? 'yellow'
                            : a.status === 'approved'
                              ? 'green'
                              : 'red'
                        }
                      >
                        {a.status}
                      </Badge>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm" c="dimmed">
                        {new Date(a.created_at).toLocaleString()}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      {a.status === 'pending' ? (
                        <Button
                          size="compact-sm"
                          variant="light"
                          onClick={() => setResolveModal(a)}
                        >
                          Handle
                        </Button>
                      ) : null}
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        )}
      </Paper>

      <Paper withBorder p="md">
        <Text fw={600} mb="sm">
          Tickets
        </Text>
        {isError && (
          <Alert color="red">
            Could not load tickets. Make sure the admin API is reachable.
          </Alert>
        )}
        {isPending && <Loader mx="auto" my="xl" />}
        {!isPending && tickets.length === 0 && (
          <Text c="dimmed">No tickets yet.</Text>
        )}
        {!isPending && tickets.length > 0 && (
          <div style={{ overflow: 'auto' }}>
            <Table.ScrollContainer minWidth={760}>
              <Table highlightOnHover verticalSpacing="sm">
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>User</Table.Th>
                    <Table.Th>Subject</Table.Th>
                    <Table.Th>Status</Table.Th>
                    <Table.Th>Received</Table.Th>
                    <Table.Th />
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {tickets.map((t) => (
                    <Table.Tr key={t.id}>
                      <Table.Td>
                        <Group gap="xs">
                          <Text fw={600}>{t.username}</Text>
                        </Group>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm">{t.subject}</Text>
                        <Text size="xs" c="dimmed" lineClamp={1}>
                          {t.message}
                        </Text>
                      </Table.Td>
                      <Table.Td>
                        <Badge color={t.status === 'closed' ? 'gray' : 'blue'}>
                          {t.status}
                        </Badge>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm" c="dimmed">
                          {new Date(t.created_at).toLocaleString()}
                        </Text>
                      </Table.Td>
                      <Table.Td>
                        <Button
                          size="compact-sm"
                          variant="light"
                          onClick={() => setReplyId(t.id)}
                        >
                          Reply
                        </Button>
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </Table.ScrollContainer>
          </div>
        )}
      </Paper>

      <Modal
        opened={replyId !== null}
        onClose={() => setReplyId(null)}
        title="Reply to ticket"
      >
        <Stack>
          <Textarea
            label="Reply message"
            rows={5}
            value={replyText}
            onChange={(e) => setReplyText(e.currentTarget.value)}
          />
          <Group>
            <label>
              <input
                type="checkbox"
                checked={alsoEmail}
                onChange={(e) => setAlsoEmail(e.target.checked)}
              />{' '}
              Also email the user
            </label>
          </Group>
          {error && <Alert color="red">{error}</Alert>}
          <Group justify="flex-end">
            <Button
              loading={send.isPending}
              onClick={() =>
                send.mutate(
                  { reply: replyText, email: alsoEmail },
                  {
                    onError: (e) =>
                      setError(e instanceof ApiError ? e.message : 'Send failed'),
                  },
                )
              }
            >
              Send reply
            </Button>
          </Group>
        </Stack>
      </Modal>

      <Modal
        opened={resolveModal !== null}
        onClose={() => setResolveModal(null)}
        title={`Appeal by ${resolveModal?.user.username ?? ''}`}
      >
        <Stack>
          <Text size="sm" c="dimmed" style={{ whiteSpace: 'pre-wrap' }}>
            {resolveModal?.message}
          </Text>
          <Textarea
            label="Resolution note"
            rows={3}
            placeholder="Optional note for the user / records"
            value={resolution}
            onChange={(e) => setResolution(e.currentTarget.value)}
          />
          {appealError && <Alert color="red">{appealError}</Alert>}
          <Group justify="flex-end">
            <Button
              variant="light"
              color="red"
              loading={resolve.isPending}
              onClick={() => runResolve('reject')}
            >
              Reject (keep suspended)
            </Button>
            <Button
              color="green"
              loading={resolve.isPending}
              onClick={() => runResolve('approve')}
            >
              Approve (unsuspend)
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  )
}