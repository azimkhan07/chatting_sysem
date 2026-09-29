import { Badge, Group, Pagination, Table, Text } from '@mantine/core'

export interface UserRow {
  id: number
  username: string
  name: string | null
  email: string
  country: string | null
  status: string
  verified: boolean
  joined: string
}

export function UserDataTable({
  rows,
  page,
  total,
  perPage,
  onPageChange,
}: {
  rows: UserRow[]
  page: number
  total: number
  perPage: number
  onPageChange: (page: number) => void
}) {
  const badgeColor =
    (status: string) =>
    status === 'suspended'
      ? 'red'
      : status === 'deactivated'
        ? 'gray'
        : status === 'suspicious'
          ? 'orange'
          : 'green'

  return (
    <>
      <Table.ScrollContainer minWidth={900}>
        <Table striped highlightOnHover verticalSpacing="sm">
          <Table.Thead>
            <Table.Tr>
              <Table.Th>Username</Table.Th>
              <Table.Th>Name</Table.Th>
              <Table.Th>Email</Table.Th>
              <Table.Th>Country</Table.Th>
              <Table.Th>Status</Table.Th>
              <Table.Th>Flagged</Table.Th>
              <Table.Th>Joined</Table.Th>
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {rows.map((u) => (
              <Table.Tr key={u.id}>
                <Table.Td>
                  <Group gap="xs">
                    <Text fw={600}>{u.username}</Text>
                    {u.verified && (
                      <Badge size="xs" color="blue" variant="light">
                        ✓
                      </Badge>
                    )}
                  </Group>
                </Table.Td>
                <Table.Td>{u.name ?? '—'}</Table.Td>
                <Table.Td>{u.email}</Table.Td>
                <Table.Td>
                  {u.country ? (
                    <Badge size="sm" variant="default">
                      {u.country}
                    </Badge>
                  ) : (
                    '—'
                  )}
                </Table.Td>
                <Table.Td>
                  <Badge size="sm" color={badgeColor(u.status)}>
                    {u.status || 'active'}
                  </Badge>
                </Table.Td>
                <Table.Td>
                  <Badge size="sm" color="gray" variant="light">
                    {u.verified ? '—' : 'unflagged'}
                  </Badge>
                </Table.Td>
                <Table.Td>
                  <Text size="sm" c="dimmed">
                    {u.joined ? new Date(u.joined).toLocaleDateString() : '—'}
                  </Text>
                </Table.Td>
              </Table.Tr>
            ))}
            {rows.length === 0 && (
              <Table.Tr>
                <Table.Td colSpan={7}>
                  <Text ta="center" c="dimmed" py="lg">
                    No users match this filter.
                  </Text>
                </Table.Td>
              </Table.Tr>
            )}
          </Table.Tbody>
        </Table>
      </Table.ScrollContainer>
      {Math.ceil(total / perPage) > 1 && (
        <Group justify="center" mt="md">
          <Pagination
            total={Math.ceil(total / perPage)}
            value={page}
            onChange={onPageChange}
          />
        </Group>
      )}
    </>
  )
}