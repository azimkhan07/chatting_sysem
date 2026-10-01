import { Alert, Badge, Button, Group, Loader, Paper, Table, Text } from '@mantine/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError } from '../lib/api'
import { useCan } from '../lib/permissions'

interface ReviewUser {
  id: number
  username: string
  display_name: string
  is_verified: boolean
}

interface ReviewSubscription {
  id: number
  status: string
  status_code: string
  plan: string
  plan_name: string
  amount_paisa: number
  paid: boolean
  is_verified: boolean
  created_at: string | null
  user: ReviewUser | null
}

const STATUS_COLOR: Record<string, string> = {
  pending: 'yellow',
  approved: 'green',
  active: 'green',
  rejected: 'red',
  cancelled: 'gray',
}

/**
 * Paid verifications waiting on a decision.
 *
 * Kept beside the pricing screen because both are "the money side", but it is
 * a queue: support works it, a moderator reads it.
 */
export default function SubscriptionQueue() {
  const qc = useQueryClient()
  const canReview = useCan()('reviewSubscriptions')
  const [error, setError] = useState<string | null>(null)

  const query = useQuery({
    queryKey: ['admin', 'subscription-queue'],
    queryFn: () => adminApi.get<{ subscriptions: ReviewSubscription[] }>('/admin/subscriptions?limit=50'),
  })

  const decide = useMutation({
    mutationFn: ({ id, action }: { id: number; action: 'approve' | 'reject' }) =>
      adminApi.post(`/admin/subscriptions/${id}/${action}`),
    onSuccess: () => {
      setError(null)
      void qc.invalidateQueries({ queryKey: ['admin', 'subscription-queue'] })
    },
    onError: (e) => setError(e instanceof ApiError ? e.message : 'Could not update the subscription.'),
  })

  const rows = query.data?.subscriptions ?? []

  return (
    <Paper withBorder p="md">
      <Text fw={600} mb="xs">
        Verification queue
      </Text>
      <Text size="xs" c="dimmed" mb="md">
        Approving turns the blue badge on for the profile; rejecting refunds the payment.
      </Text>

      {!canReview && (
        <Alert color="gray" variant="light" mb="md">
          View only — approving or rejecting a purchase is a support or admin action.
        </Alert>
      )}
      {error && (
        <Alert color="red" mb="md">
          {error}
        </Alert>
      )}

      {query.isError && <Alert color="red">Could not load subscriptions.</Alert>}
      {query.isPending && <Loader mx="auto" my="lg" />}
      {!query.isPending && rows.length === 0 && <Text c="dimmed">No subscriptions yet.</Text>}

      {!query.isPending && rows.length > 0 && (
        <Table.ScrollContainer minWidth={760}>
          <Table highlightOnHover verticalSpacing="sm">
            <Table.Thead>
              <Table.Tr>
                <Table.Th>User</Table.Th>
                <Table.Th>Plan</Table.Th>
                <Table.Th>Amount</Table.Th>
                <Table.Th>Paid</Table.Th>
                <Table.Th>Status</Table.Th>
                <Table.Th>Bought</Table.Th>
                <Table.Th />
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {rows.map((s) => (
                <Table.Tr key={s.id}>
                  <Table.Td>
                    <Text size="sm">{s.user ? `@${s.user.username}` : 'account deleted'}</Text>
                    {s.user && (
                      <Text size="xs" c="dimmed">
                        {s.user.display_name}
                      </Text>
                    )}
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">{s.plan_name}</Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">₹{(s.amount_paisa / 100).toFixed(2)}</Text>
                  </Table.Td>
                  <Table.Td>
                    <Badge size="sm" color={s.paid ? 'green' : 'gray'} variant="light">
                      {s.paid ? 'yes' : 'no'}
                    </Badge>
                  </Table.Td>
                  <Table.Td>
                    <Badge size="sm" color={STATUS_COLOR[s.status_code] ?? 'gray'}>
                      {s.status}
                    </Badge>
                  </Table.Td>
                  <Table.Td>
                    <Text size="xs" c="dimmed">
                      {s.created_at ? new Date(s.created_at).toLocaleDateString() : '—'}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    {canReview && s.status_code === 'pending' ? (
                      <Group gap="xs">
                        <Button
                          size="compact-sm"
                          variant="light"
                          color="green"
                          loading={decide.isPending}
                          onClick={() => decide.mutate({ id: s.id, action: 'approve' })}
                        >
                          Approve
                        </Button>
                        <Button
                          size="compact-sm"
                          variant="subtle"
                          color="red"
                          loading={decide.isPending}
                          onClick={() => decide.mutate({ id: s.id, action: 'reject' })}
                        >
                          Reject
                        </Button>
                      </Group>
                    ) : (
                      <Text size="xs" c="dimmed">
                        {canReview ? 'closed' : 'View only'}
                      </Text>
                    )}
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      )}
    </Paper>
  )
}
