import {
  Alert,
  Badge,
  Button,
  Group,
  Loader,
  Modal,
  Paper,
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

export default function Support() {
  const qc = useQueryClient()
  const [replyId, setReplyId] = useState<number | null>(null)
  const [replyText, setReplyText] = useState('')
  const [alsoEmail, setAlsoEmail] = useState(false)
  const [error, setError] = useState<string | null>(null)

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

  const tickets = data?.tickets ?? []

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Support
      </Text>
      {isError && (
        <Alert color="red">
          Could not load tickets. Make sure the admin API is reachable.
        </Alert>
      )}
      {isPending && <Loader mx="auto" my="xl" />}
      {!isPending && tickets.length === 0 && (
        <Paper withBorder p="md">
          <Text c="dimmed">No tickets yet.</Text>
        </Paper>
      )}
      {!isPending && tickets.length > 0 && (
        <Paper withBorder p="md" style={{ overflow: 'auto' }}>
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
        </Paper>
      )}

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
    </Stack>
  )
}