import {
  Card,
  Group,
  Loader,
  Paper,
  Select,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
} from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi } from '../lib/api'
import { UserDataTable } from '../components/UserDataTable'

interface UsersResponse {
  users: Array<{
    id: number
    username: string
    display_name: string
    email: string
    country: string | null
    status: string
    is_verified: boolean
    created_at: string
  }>
  meta: { total: number; page: number; per_page: number }
}

export default function Users() {
  const [filter, setFilter] = useState<'all' | 'suspicious' | 'suspended' | 'deactivated'>('all')
  const [country, setCountry] = useState<string>('all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const { data, isPending, isError, error } = useQuery({
    queryKey: ['admin', 'users', filter, country, search, page],
    queryFn: () => {
      const params = new URLSearchParams({
        status: filter,
        page: String(page),
        limit: '25',
      })
      if (country !== 'all' && country) params.set('country', country)
      if (search) params.set('search', search)
      return adminApi.get<UsersResponse>(`/admin/users?${params}`)
    },
  })

  const statCards = [
    { label: 'All users', value: 0 },
    { label: 'Joined today', value: 0 },
    { label: 'Joined this month', value: 0 },
    { label: 'Joined this year', value: 0 },
  ]

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Users
      </Text>

      <Paper withBorder p="md">
        <Text size="sm" c="dimmed" mb="md">
          Summary
        </Text>
        <SimpleGrid cols={{ base: 1, xs: 2, lg: 4 }}>
          {statCards.map((c) => (
            <Card key={c.label} withBorder radius="md" p="md">
              <Text size="sm" c="dimmed">
                {c.label}
              </Text>
              <Text fw={800} size="xl">
                {c.value}
              </Text>
            </Card>
          ))}
        </SimpleGrid>
      </Paper>

      <Group grow>
        <Select
          label="Status filter"
          value={filter}
          allowDeselect={false}
          onChange={(v) => {
            setFilter(v as typeof filter)
            setPage(1)
          }}
          data={[
            { value: 'all', label: 'All users' },
            { value: 'suspicious', label: 'Suspicious users' },
            { value: 'suspended', label: 'Suspended users' },
            { value: 'deactivated', label: 'Deactivated users' },
          ]}
        />
        <Select
          label="Country"
          placeholder="All countries"
          searchable
          clearable
          data={[]}
          onChange={(v) => {
            setCountry(v ?? 'all')
            setPage(1)
          }}
        />
        <TextInput
          label="Search"
          placeholder="Username, name or email"
          value={search}
          onChange={(e) => {
            setSearch(e.currentTarget.value)
            setPage(1)
          }}
        />
      </Group>

      {isError && (
        <Paper withBorder p="md" c="red">
          {error instanceof Error ? error.message : 'Failed to load users.'}
        </Paper>
      )}

      <Paper withBorder p="md" style={{ position: 'relative', minHeight: 160 }}>
        {isPending && <Loader my="xl" mx="auto" />}
        {!isPending && data && (
          <UserDataTable
            rows={
              data.users?.map((u) => ({
                id: u.id,
                username: u.username,
                name: u.display_name,
                email: u.email,
                country: u.country,
                status: u.status,
                verified: u.is_verified,
                joined: u.created_at,
              })) ?? []
            }
            page={data.meta?.page ?? 1}
            total={data.meta?.total ?? 0}
            perPage={data.meta?.per_page ?? 25}
            onPageChange={(p) => {
              setPage(p)
              window.scrollTo({ top: 0 })
            }}
          />
        )}
      </Paper>
    </Stack>
  )
}