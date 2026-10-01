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
import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError } from '../lib/api'
import { useCan } from '../lib/permissions'

interface ReportUser {
  id: number
  username: string
  display_name: string
  status?: string
}

interface Report {
  id: number
  target_type: string
  target_type_label: string
  target_id: number
  reason: string
  reason_label: string
  is_urgent: boolean
  details: string | null
  status: string
  reporter: ReportUser | null
  target_user: ReportUser | null
  handled_by: { id: number; username: string } | null
  resolution: string | null
  handled_at: string | null
  created_at: string | null
}

const DECISIONS = [
  { value: 'reviewing', label: 'Take it for review' },
  { value: 'actioned', label: 'Acted on it' },
  { value: 'dismissed', label: 'Dismissed' },
]

const STATUS_COLOR: Record<string, string> = {
  pending: 'yellow',
  reviewing: 'blue',
  actioned: 'green',
  dismissed: 'gray',
}

/**
 * The open report queue: posts and comments other accounts flagged.
 *
 * The API returns only pending/reviewing rows, cursor paged and ordered so
 * self-harm and abuse sit on top. A moderator reads this queue; closing a
 * report is a write the `operations` gate would refuse, so no buttons render
 * for that role.
 */
export default function Reports() {
  const qc = useQueryClient()
  const canResolveReports = useCan()('resolveReports')
  const [decision, setDecision] = useState({ report: null as Report | null, next: 'reviewing' })
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)

  // Cursor paging, accumulated. The queue is ordered so self-harm and abuse sit
  // on top, and it is the one console list that grows without bound from member
  // action alone, so fetching a single page and saying "more are queued" was
  // not a summary of the queue - it made everything past the first 25 rows
  // unreachable. A moderator could not work the queue past page one at all.
  const query = useInfiniteQuery({
    queryKey: ['admin', 'reports'],
    initialPageParam: undefined as string | undefined,
    queryFn: ({ pageParam }) => {
      const cursor = pageParam === undefined ? '' : `&cursor=${encodeURIComponent(pageParam)}`
      return adminApi.get<{ reports: Report[]; next_cursor: string | null }>(
        `/admin/reports?limit=25${cursor}`,
      )
    },
    getNextPageParam: (last) => last.next_cursor ?? undefined,
  })

  const resolve = useMutation({
    mutationFn: ({ id, next, resolution }: { id: number; next: string; resolution: string | null }) =>
      adminApi.patch(`/admin/reports/${id}`, { status: next, resolution }),
    onSuccess: () => {
      setDecision({ report: null, next: 'reviewing' })
      setNote('')
      // A closed report leaves the pending/reviewing filter, so every loaded
      // page shifts. Invalidating drops the accumulation rather than patching
      // one page and leaving a duplicate or a hole in the list.
      void qc.invalidateQueries({ queryKey: ['admin', 'reports'] })
    },
    onError: (e) => setError(e instanceof ApiError ? e.message : 'Could not update the report.'),
  })

  const reports = query.data?.pages.flatMap((p) => p.reports) ?? []

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Text fz="lg" fw={700}>
          Report queue
        </Text>
        <Button
          size="compact-sm"
          variant="default"
          loading={query.isFetching}
          onClick={() => void qc.invalidateQueries({ queryKey: ['admin', 'reports'] })}
        >
          Refresh
        </Button>
      </Group>

      <Text size="xs" c="dimmed">
        Open reports only, self-harm and abuse first.
      </Text>

      {!canResolveReports && (
        <Alert color="gray" variant="light">
          View only — a moderator can read this queue but cannot close a report.
        </Alert>
      )}
      {query.isError && <Alert color="red">Could not load the report queue.</Alert>}
      {query.isPending && <Loader mx="auto" my="xl" />}
      {!query.isPending && reports.length === 0 && <Text c="dimmed">Nothing waiting. The queue is clear.</Text>}

      {!query.isPending && reports.length > 0 && (
        <Paper withBorder p="md">
          <Table.ScrollContainer minWidth={860}>
            <Table highlightOnHover verticalSpacing="sm">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Reported</Table.Th>
                  <Table.Th>Reporter</Table.Th>
                  <Table.Th>Reason</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th>Filed</Table.Th>
                  <Table.Th />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {reports.map((r) => (
                  <Table.Tr key={r.id}>
                    <Table.Td>
                      <Text size="sm">
                        {r.target_type_label} #{r.target_id}
                      </Text>
                      <Text size="xs" c="dimmed">
                        {r.target_user ? `@${r.target_user.username}` : 'account deleted'}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{r.reporter ? `@${r.reporter.username}` : '—'}</Text>
                    </Table.Td>
                    <Table.Td>
                      <Group gap={6} wrap="nowrap">
                        <Text size="sm">{r.reason_label}</Text>
                        {r.is_urgent && (
                          <Badge size="xs" color="red" variant="filled">
                            urgent
                          </Badge>
                        )}
                      </Group>
                      {r.details && (
                        <Text size="xs" c="dimmed" lineClamp={2}>
                          {r.details}
                        </Text>
                      )}
                    </Table.Td>
                    <Table.Td>
                      <Badge size="sm" color={STATUS_COLOR[r.status] ?? 'gray'}>
                        {r.status}
                      </Badge>
                    </Table.Td>
                    <Table.Td>
                      <Text size="xs" c="dimmed">
                        {r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      {canResolveReports ? (
                        <Button
                          size="compact-sm"
                          variant="light"
                          onClick={() => {
                            setError(null)
                            setNote(r.resolution ?? '')
                            setDecision({ report: r, next: r.status === 'reviewing' ? 'actioned' : 'reviewing' })
                          }}
                        >
                          Decide
                        </Button>
                      ) : (
                        <Text size="xs" c="dimmed">
                          View only
                        </Text>
                      )}
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>

          {query.hasNextPage && (
            <Group justify="center" mt="sm">
              <Button
                variant="default"
                size="compact-sm"
                loading={query.isFetchingNextPage}
                onClick={() => void query.fetchNextPage()}
              >
                Load {reports.length} more
              </Button>
            </Group>
          )}

          {!query.hasNextPage && reports.length > 0 && (
            <Group justify="center" mt="sm">
              <Text size="xs" c="dimmed">
                End of queue · {reports.length} shown
              </Text>
            </Group>
          )}
        </Paper>
      )}

      <Modal
        opened={decision.report !== null}
        onClose={() => setDecision({ report: null, next: 'reviewing' })}
        title="Close this report"
      >
        <Stack>
          <Select
            label="Decision"
            data={DECISIONS}
            value={decision.next}
            onChange={(v) => setDecision((d) => ({ ...d, next: v ?? 'reviewing' }))}
            allowDeselect={false}
          />
          <Textarea
            label="Resolution note"
            rows={3}
            value={note}
            onChange={(e) => setNote(e.currentTarget.value)}
          />
          {error && <Alert color="red">{error}</Alert>}
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setDecision({ report: null, next: 'reviewing' })}>
              Cancel
            </Button>
            <Button
              loading={resolve.isPending}
              onClick={() =>
                decision.report &&
                resolve.mutate({
                  id: decision.report.id,
                  next: decision.next,
                  resolution: note.trim() === '' ? null : note.trim(),
                })
              }
            >
              Save
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  )
}
