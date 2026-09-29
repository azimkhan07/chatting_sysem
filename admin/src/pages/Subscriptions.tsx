import {
  Alert,
  Button,
  Checkbox,
  Group,
  NumberInput,
  Paper,
  Select,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError } from '../lib/api'

const CURRENCY_BY_COUNTRY: Record<string, { code: string; symbol: string }> = {
  IN: { code: 'INR', symbol: '₹' },
  US: { code: 'USD', symbol: '$' },
  AE: { code: 'AED', symbol: 'د.إ' },
  GB: { code: 'GBP', symbol: '£' },
  CA: { code: 'CAD', symbol: 'C$' },
  AU: { code: 'AUD', symbol: 'A$' },
  DE: { code: 'EUR', symbol: '€' },
  FR: { code: 'EUR', symbol: '€' },
}

const FEATURES = [
  { key: 'calls', label: 'Voice & video calls' },
  { key: 'stories', label: 'Stories' },
  { key: 'groups', label: 'Groups & families' },
  { key: 'archive', label: 'Archive' },
  { key: 'saved', label: 'Saved collections' },
  { key: 'export', label: 'JSON export' },
  { key: 'badge', label: 'Blue tick (verified)' },
  { key: 'priority', label: 'Priority support' },
]

interface PricingPayload {
  plan: string
  country: string
  currency: string
  currency_symbol: string
  price_month_paisa: number
  features: string[]
}

export default function Subscriptions() {
  const qc = useQueryClient()
  const [plan, setPlan] = useState<'simple' | 'standard' | 'premium'>('standard')

  const form = useForm<PricingPayload>({
    initialValues: {
      plan,
      country: '',
      currency: '',
      currency_symbol: '',
      price_month_paisa: 50000,
      features:
        plan === 'simple'
          ? ['calls']
          : plan === 'standard'
            ? ['calls', 'stories', 'groups', 'archive', 'saved', 'export']
            : FEATURES.map((f) => f.key),
    },
    validate: {
      country: (v) => (!v ? 'Country is required' : null),
      currency_symbol: (v) => (!v ? 'Pick a country to set currency' : null),
      price_month_paisa: (v) => (!v || v < 0 ? 'Enter a valid price' : null),
    },
  })

  const save = useMutation({
    mutationFn: (values: PricingPayload) =>
      adminApi.post<{ saved: boolean }>('/admin/plans/pricing', { ...values, plan }),
    onSuccess: () => {
      form.setFieldValue('plan', plan)
      void qc.invalidateQueries({ queryKey: ['admin', 'plan-countries', plan] })
    },
  })

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Subscriptions
      </Text>

      <Group>
        {(['simple', 'standard', 'premium'] as const).map((p) => (
          <Button
            key={p}
            variant={plan === p ? 'filled' : 'default'}
            onClick={() => {
              setPlan(p)
              save.mutate(form.values)
            }}
          >
            {p[0].toUpperCase() + p.slice(1)}
          </Button>
        ))}
      </Group>

      <Paper withBorder p="md">
        <Text fw={600} mb="md">
          Country-wise pricing · {plan[0].toUpperCase() + plan.slice(1)}
        </Text>
        <form
          onSubmit={form.onSubmit((values) => {
            save.mutate(values)
          })}
        >
          <SimpleGrid cols={{ base: 1, md: 2 }}>
            <Select
              label="Country"
              searchable
              data={Object.entries(CURRENCY_BY_COUNTRY).map(([code, c]) => ({
                value: code,
                label: `${code} — ${c.symbol}`,
              }))}
              value={form.values.country || null}
              onChange={(v) => {
                form.setFieldValue('country', v ?? '')
                const cur = v ? CURRENCY_BY_COUNTRY[v] : undefined
                form.setFieldValue('currency', cur?.code ?? '')
                form.setFieldValue('currency_symbol', cur?.symbol ?? '')
              }}
              error={form.errors.country}
            />
            <TextInput label="Currency" readOnly {...form.getInputProps('currency')} />
            <TextInput label="Currency symbol" readOnly {...form.getInputProps('currency_symbol')} />
            <NumberInput
              label="Monthly price (paisa)"
              min={0}
              step={100}
              {...form.getInputProps('price_month_paisa')}
            />
          </SimpleGrid>

          <Text fw={600} mt="lg" mb="sm">
            Features unlocked by this plan
          </Text>
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
            {FEATURES.map((f) => (
              <Checkbox
                key={f.key}
                label={f.label}
                checked={form.values.features.includes(f.key)}
                onChange={(e) => {
                  const checked = e.currentTarget.checked
                  form.setFieldValue(
                    'features',
                    checked
                      ? [...form.values.features, f.key]
                      : form.values.features.filter((k) => k !== f.key),
                  )
                }}
              />
            ))}
          </SimpleGrid>

          {save.isSuccess && <Alert color="green" mt="md">Pricing saved.</Alert>}
          {save.isError && (
            <Alert color="red" mt="md">
              {save.error instanceof ApiError ? save.error.message : 'Save failed'}
            </Alert>
          )}

          <Group mt="lg">
            <Button type="submit" loading={save.isPending}>
              Save pricing
            </Button>
          </Group>
        </form>
      </Paper>
    </Stack>
  )
}