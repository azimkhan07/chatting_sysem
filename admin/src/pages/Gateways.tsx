import {
  Alert,
  Button,
  Group,
  Loader,
  Paper,
  SimpleGrid,
  Stack,
  Switch,
  Text,
  TextInput,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { adminApi, ApiError } from '../lib/api'

interface GatewayConfig {
  key: string
  merchant_id: string
  secret: string
  endpoint: string
  currency: string
  enabled: boolean
}

export default function Gateways() {
  const qc = useQueryClient()

  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'gateways'],
    queryFn: () => adminApi.get<{ gateways: GatewayConfig[] }>('/admin/gateways'),
  })

  const form = useForm<GatewayConfig>({
    initialValues: { key: '', merchant_id: '', secret: '', endpoint: '', currency: 'INR', enabled: true },
  })

  const save = useMutation({
    mutationFn: (values: GatewayConfig) =>
      adminApi.post<{ saved: boolean }>('/admin/gateways', values),
    onSuccess: () => {
      form.reset()
      void qc.invalidateQueries({ queryKey: ['admin', 'gateways'] })
    },
  })

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Payment gateways
      </Text>
      {isError && (
        <Alert color="red">Could not load gateway config. Backend endpoint needed.</Alert>
      )}
      {isPending && <Loader mx="auto" my="xl" />}

      {!isPending && (
        <Paper withBorder p="md">
          <Text fw={600} mb="md">
            Configured gateways
          </Text>
          {(data?.gateways ?? []).length === 0 && (
            <Text c="dimmed">No payment gateways configured yet.</Text>
          )}
          {data?.gateways.map((g, i) => (
            <Group key={i} justify="space-between" mb="sm">
              <Stack gap={0}>
                <Text fw={600}>{g.key}</Text>
                <Text size="xs" c="dimmed">
                  {g.endpoint} · {g.currency}
                </Text>
              </Stack>
              <Text size="sm" color={g.enabled ? 'green' : 'red'}>
                {g.enabled ? 'enabled' : 'disabled'}
              </Text>
            </Group>
          ))}
        </Paper>
      )}

      <Paper withBorder p="md">
        <Text fw={600} mb="md">
          Add / update gateway credential
        </Text>
        <form onSubmit={form.onSubmit((v) => save.mutate(v))}>
          <SimpleGrid cols={{ base: 1, md: 2 }}>
            <TextInput label="Gateway key" placeholder="razorpay_live_xxx" {...form.getInputProps('key')} />
            <TextInput label="Merchant id" {...form.getInputProps('merchant_id')} />
            <TextInput label="Secret key" {...form.getInputProps('secret')} />
            <TextInput label="Endpoint" placeholder="https://api.razorpay.com/v1" {...form.getInputProps('endpoint')} />
            <TextInput label="Currency" {...form.getInputProps('currency')} />
          </SimpleGrid>
          <Group mt="md">
            <Switch
              label="Enabled"
              checked={form.values.enabled}
              onChange={(e) => form.setFieldValue('enabled', e.currentTarget.checked)}
            />
            <Button type="submit" loading={save.isPending}>
              Save gateway
            </Button>
          </Group>
          {save.isError && (
            <Alert color="red" mt="md">
              {save.error instanceof ApiError ? save.error.message : 'Save failed'}
            </Alert>
          )}
        </form>
      </Paper>
    </Stack>
  )
}