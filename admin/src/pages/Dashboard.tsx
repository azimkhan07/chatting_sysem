import {
  Card,
  Group,
  Loader,
  NumberFormatter,
  Paper,
  Select,
  SimpleGrid,
  Stack,
  Text,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi } from '../lib/api'

interface DashboardStats {
  users: number
  users_today: number
  users_month: number
  users_year: number
  active_subscriptions: number
  suspended_users: number
  verified_users: number
  revenue_paisa: number
}

export default function Dashboard() {
  const [range, setRange] = useState<'today' | 'month' | 'year' | 'custom'>('month')
  const [custom, setCustom] = useState<[string | null, string | null]>([null, null])

  const inRange = range !== 'custom' || (custom[0] !== null && custom[1] !== null)

  const { data, isPending, isError, error } = useQuery({
    queryKey: ['admin', 'dashboard', range, custom],
    queryFn: () => {
      const params = new URLSearchParams({ range })
      if (range === 'custom' && custom[0] && custom[1]) {
        params.set('from', custom[0])
        params.set('to', custom[1])
      }
      return adminApi.get<DashboardStats>(`/admin/dashboard/stats?${params}`)
    },
    enabled: inRange,
  })

  const cards = [
    { label: 'Total users', value: data?.users, color: 'blue', icon: '👤' },
    { label: 'Active subscriptions', value: data?.active_subscriptions, color: 'green', icon: '💳' },
    { label: 'Suspended users', value: data?.suspended_users, color: 'red', icon: '🚫' },
    { label: 'Verified (blue tick)', value: data?.verified_users, color: 'grape', icon: '✅' },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Text fz="lg" fw={700}>
          Dashboard
        </Text>
        <Group>
          <Select
            size="xs"
            value={range}
            onChange={(v) => v && setRange(v as typeof range)}
            data={[
              { value: 'today', label: 'Today' },
              { value: 'month', label: 'This month' },
              { value: 'year', label: 'This year' },
              { value: 'custom', label: 'Custom…' },
            ]}
            w={150}
            allowDeselect={false}
          />
          {range === 'custom' && (
            <DatePickerInput
              size="xs"
              type="range"
              placeholder="Pick start → end"
              value={custom}
              onChange={(v) => setCustom(v)}
              clearable={false}
              maxDate={new Date()}
              w={260}
            />
          )}
        </Group>
      </Group>

      {isPending && <Loader my="xl" mx="auto" />}
      {isError && (
        <Paper withBorder p="md" c="red">
          {error instanceof Error ? error.message : 'Failed to load dashboard.'}
        </Paper>
      )}
      {inRange && !isPending && !isError && data && (
        <SimpleGrid cols={{ base: 1, xs: 2, lg: 4 }}>
          {cards.map((c) => (
            <Card key={c.label} withBorder radius="md" p="md">
              <Group justify="space-between" wrap="nowrap">
                <div>
                  <Text size="sm" c="dimmed">
                    {c.label}
                  </Text>
                  <Text fw={800} size="xl">
                    <NumberFormatter value={c.value ?? 0} thousandSeparator />
                  </Text>
                </div>
                <Text fz="xl">{c.icon}</Text>
              </Group>
            </Card>
          ))}
        </SimpleGrid>
      )}
    </Stack>
  )
}