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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError } from '../lib/api'

interface FeatureCatalogueItem {
  key: string
  label: string
  blurb: string | null
}

interface CountryItem {
  code: string
  name: string
  currency: string
  symbol: string
}

interface PricingPayload {
  plan: string
  country: string
  currency: string
  currency_symbol: string
  price_month_paisa: number
  features: string[]
}

const PLAN_DEFAULT_FEATURES: Record<string, string[]> = {
  simple: ['calls'],
  standard: ['calls', 'stories', 'groups', 'archive', 'saved', 'export'],
  premium: [], // filled from the API list live in the component
}

export default function Subscriptions() {
  const qc = useQueryClient()
  const [plan, setPlan] = useState<'simple' | 'standard' | 'premium'>('standard')

  const { data: featuresData } = useQuery({
    queryKey: ['admin', 'features'],
    queryFn: () =>
      adminApi.get<{ features: FeatureCatalogueItem[] }>('/admin/features'),
  })

  const { data: countriesData } = useQuery({
    queryKey: ['admin', 'countries'],
    queryFn: () => adminApi.get<{ countries: CountryItem[] }>('/admin/countries'),
  })

  const features = featuresData?.features ?? []
  const countries = countriesData?.countries ?? []

  const countryByCode = Object.fromEntries(
    countries.map((c) => [c.code, c]),
  )

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
            : [],
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
      void qc.invalidateQueries({ queryKey: ['admin', 'plan-countries', plan] })
    },
  })

  function switchPlan(next: 'simple' | 'standard' | 'premium') {
    setPlan(next)
    if (next === 'premium') {
      form.setFieldValue('features', features.map((f) => f.key))
    } else {
      form.setFieldValue('features', PLAN_DEFAULT_FEATURES[next])
    }
  }

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Subscriptions
      </Text>
      <Text size="sm" c="dimmed">
        Feature checkboxes load from the backend catalogue, so any new keyword
        registered on the server shows up here automatically.
      </Text>

      <Group>
        {(['simple', 'standard', 'premium'] as const).map((p) => (
          <Button
            key={p}
            variant={plan === p ? 'filled' : 'default'}
            onClick={() => switchPlan(p)}
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
              data={countries.map((c) => ({
                value: c.code,
                label: `${c.name} (${c.code})`,
              }))}
              value={form.values.country || null}
              onChange={(v) => {
                form.setFieldValue('country', v ?? '')
                const cur = v ? countryByCode[v] : undefined
                form.setFieldValue('currency', cur?.currency ?? '')
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
          {features.length === 0 ? (
            <Text size="sm" c="dimmed">
              Loading feature catalogue…
            </Text>
          ) : (
            <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
              {features.map((f) => (
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
          )}

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