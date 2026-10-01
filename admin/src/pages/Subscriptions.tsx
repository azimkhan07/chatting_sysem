import {
  Alert,
  Badge,
  Button,
  Checkbox,
  Group,
  NumberInput,
  Paper,
  Select,
  SimpleGrid,
  Stack,
  Table,
  Text,
  TextInput,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import SubscriptionQueue from '../components/SubscriptionQueue'
import { adminApi, ApiError } from '../lib/api'
import { useCan } from '../lib/permissions'

type Plan = 'simple' | 'standard' | 'premium'

/**
 * What the server sends for one registered feature.
 *
 * `tier` is the answer to the question that decides the whole shape of this
 * form: a premium feature is bought by ticking it on a plan, a free one is
 * already unlocked for everyone and so has no box to tick. It is decided in code
 * when the feature is registered and arrives here as data - this file contains
 * no feature names at all.
 */
interface FeatureCatalogueItem {
  key: string
  label: string
  blurb: string | null
  tier: 'premium' | 'free'
}

interface CountryItem {
  code: string
  name: string
  currency: string
  symbol: string
}

interface PlanRow {
  id: number
  country: string
  currency: string
  currency_symbol: string
  price_month_paisa: number
  features: string[]
}

interface PricingPayload {
  country: string
  currency: string
  currency_symbol: string
  price_month_paisa: number
  features: string[]
}

const PLAN_LABEL: Record<Plan, string> = {
  simple: 'Simple',
  standard: 'Standard',
  premium: 'Premium',
}

const PLANS: readonly Plan[] = ['simple', 'standard', 'premium'] as const

export default function Subscriptions() {
  const qc = useQueryClient()
  const [plan, setPlan] = useState<Plan>('standard')
  const canEditPlans = useCan()('editPlans')

  // The row currently loaded into the form, or null when adding a new country to
  // this plan. Set by clicking a row in the saved table, cleared by picking a
  // different plan or country. This is what turns the form into an update form -
  // without it the page can only ever add, and correcting a price means guessing
  // which country it belongs to.
  const [editing, setEditing] = useState<PlanRow | null>(null)

  // Gated on the same capability as the form that consumes this. Both endpoints
  // sit behind `admin` on the API, and the review queue below - the part of this
  // page a support agent is actually here for - is not hidden behind that gate.
  const { data: featuresData, isLoading: featuresLoading } = useQuery({
    queryKey: ['admin', 'features'],
    queryFn: () =>
      adminApi.get<{ features: FeatureCatalogueItem[] }>('/admin/features'),
    enabled: canEditPlans,
  })

  const { data: countriesData } = useQuery({
    queryKey: ['admin', 'countries'],
    queryFn: () => adminApi.get<{ countries: CountryItem[] }>('/admin/countries'),
    enabled: canEditPlans,
  })

  // The saved rows for the plan being edited. Every change to a plan's pricing
  // happens through this page, so a stale copy here means a save that silently
  // overwrote somebody else's edit.
  const { data: rowsData, isLoading: rowsLoading } = useQuery({
    queryKey: ['admin', 'plan-countries', plan],
    queryFn: () =>
      adminApi.get<{ rows: PlanRow[] }>(`/admin/plans/${plan}/countries`),
    enabled: canEditPlans,
  })

  const features = featuresData?.features ?? []
  const countries = countriesData?.countries ?? []
  const rows = rowsData?.rows ?? []

  const premiumFeatures = features.filter((f) => f.tier === 'premium')
  const freeFeatures = features.filter((f) => f.tier !== 'premium')

  const countryByCode = Object.fromEntries(
    countries.map((c) => [c.code, c]),
  )

  const form = useForm<PricingPayload>({
    initialValues: {
      country: '',
      currency: '',
      currency_symbol: '',
      price_month_paisa: 50000,
      features: [],
    },
    validate: {
      country: (v) => (!v ? 'Country is required' : null),
      currency_symbol: (v) => (!v ? 'Pick a country to set currency' : null),
      price_month_paisa: (v) =>
        !v || v < 0 ? 'Enter a valid price' : null,
    },
  })

  const save = useMutation({
    mutationFn: (values: PricingPayload) =>
      adminApi.post<{ saved: boolean }>('/admin/plans/pricing', {
        ...values,
        plan,
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'plan-countries', plan] })
    },
  })

  /**
   * Loads a saved row into the form for editing.
   *
   * The stored feature list is intersected with the live premium catalogue
   * before it goes into the form. A row saved before a feature was retired still
   * names that keyword, and the save endpoint rejects keywords that are not live -
   * correctly, since a plan must not sell an unlock that does not exist. Without
   * this intersection the row would load, and then be impossible to save, and
   * the only way out would be to un-tick the invisible ghost by hand.
   */
  function loadRow(row: PlanRow) {
    const live = new Set(premiumFeatures.map((f) => f.key))
    form.setValues({
      country: row.country,
      currency: row.currency,
      currency_symbol: row.currency_symbol,
      price_month_paisa: row.price_month_paisa,
      features: row.features.filter((k) => live.has(k)),
    })
    setEditing(row)
    save.reset()
  }

  function startNew() {
    form.setValues({
      country: '',
      currency: '',
      currency_symbol: '',
      price_month_paisa: 50000,
      features: [],
    })
    setEditing(null)
    save.reset()
  }

  /**
   * Switching plan drops whatever was in the form.
   *
   * Feature selections deliberately carry across nothing. The old behaviour
   * pre-filled a per-plan default list, which meant clicking a plan silently
   * handed the admin a set of checkboxes nobody had chosen - and one of those
   * defaults was `calls`, a keyword the app does not implement. An empty form
   * with a visible list of what is already saved is the honest version.
   */
  function switchPlan(next: Plan) {
    setPlan(next)
    startNew()
  }

  function pickCountry(code: string | null) {
    form.setFieldValue('country', code ?? '')
    const cur = code ? countryByCode[code] : undefined
    form.setFieldValue('currency', cur?.currency ?? '')
    form.setFieldValue('currency_symbol', cur?.symbol ?? '')

    // Picking a different country means this is no longer the row we loaded. If
    // that country is already priced on this plan, load it instead of starting
    // blank, so the admin is not invited to type a price that already exists.
    if (code) {
      const existing = rows.find((r) => r.country === code)
      if (existing && existing.id !== editing?.id) {
        loadRow(existing)
      } else if (!existing) {
        setEditing(null)
      }
    }
  }

  return (
    <Stack gap="md">
      <Text fz="lg" fw={700}>
        Subscriptions
      </Text>
      <Text size="sm" c="dimmed">
        Checkboxes come from the server's registered features, so a feature added
        in code appears here without rebuilding this app. Paid features are
        something a plan can sell; free features are unlocked for everyone and
        are not listed on a plan.
      </Text>

      <Group>
        {PLANS.map((p) => (
          <Button
            key={p}
            variant={plan === p ? 'filled' : 'default'}
            onClick={() => switchPlan(p)}
          >
            {PLAN_LABEL[p]}
          </Button>
        ))}
      </Group>

      {canEditPlans && (
        <Paper withBorder p="md">
          <Group justify="space-between" mb="sm">
            <Text fw={600}>
              Saved pricing · {PLAN_LABEL[plan]}
            </Text>
            <Button size="xs" variant="light" onClick={startNew}>
              Add a country
            </Button>
          </Group>

          {rowsLoading ? (
            <Text size="sm" c="dimmed">
              Loading saved pricing…
            </Text>
          ) : rows.length === 0 ? (
            <Text size="sm" c="dimmed">
              No pricing saved for {PLAN_LABEL[plan]} yet. Pick a country below to
              add the first one.
            </Text>
          ) : (
            <Table.ScrollContainer minWidth={520}>
              <Table verticalSpacing="xs" highlightOnHover>
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>Country</Table.Th>
                    <Table.Th>Price</Table.Th>
                    <Table.Th>Paid features</Table.Th>
                    <Table.Th />
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {rows.map((r) => {
                    const country = countryByCode[r.country]
                    return (
                      <Table.Tr key={r.id}>
                        <Table.Td>
                          {country ? `${country.name} (${r.country})` : r.country}
                        </Table.Td>
                        <Table.Td>
                          {r.currency_symbol}
                          {(r.price_month_paisa / 100).toFixed(2)}
                        </Table.Td>
                        <Table.Td>
                          <Text size="sm" c="dimmed">
                            {r.features.length}
                          </Text>
                        </Table.Td>
                        <Table.Td>
                          <Button
                            size="xs"
                            variant="light"
                            onClick={() => loadRow(r)}
                          >
                            Edit
                          </Button>
                        </Table.Td>
                      </Table.Tr>
                    )
                  })}
                </Table.Tbody>
              </Table>
            </Table.ScrollContainer>
          )}
        </Paper>
      )}

      <Paper withBorder p="md">
        <Group justify="space-between" mb="md">
          <Text fw={600}>
            Country-wise pricing · {PLAN_LABEL[plan]}
          </Text>
          {editing && (
            <Badge variant="light" color="blue">
              Editing {editing.country}
            </Badge>
          )}
        </Group>
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
              onChange={pickCountry}
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
            Paid features included in this plan
          </Text>
          {featuresLoading ? (
            <Text size="sm" c="dimmed">
              Loading feature catalogue…
            </Text>
          ) : premiumFeatures.length === 0 ? (
            <Text size="sm" c="dimmed">
              No paid features are registered yet. Everything currently
              registered is free for everyone.
            </Text>
          ) : (
            <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
              {premiumFeatures.map((f) => (
                <Checkbox
                  key={f.key}
                  label={f.label}
                  description={f.blurb ?? undefined}
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

          {freeFeatures.length > 0 && (
            <>
              <Text fw={600} mt="lg" mb="sm">
                Free for everyone
              </Text>
              <Text size="sm" c="dimmed" mb="xs">
                Not sold on any plan. These unlock on every account whether or not
                it has a subscription, so they have no checkbox.
              </Text>
              <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
                {freeFeatures.map((f) => (
                  <Group key={f.key} gap="xs" wrap="nowrap" align="flex-start">
                    <Badge size="sm" variant="light" color="teal">
                      Free
                    </Badge>
                    <div>
                      <Text size="sm">{f.label}</Text>
                      {f.blurb && (
                        <Text size="xs" c="dimmed">
                          {f.blurb}
                        </Text>
                      )}
                    </div>
                  </Group>
                ))}
              </SimpleGrid>
            </>
          )}

          {save.isSuccess && (
            <Alert color="green" mt="md">
              Pricing saved for {PLAN_LABEL[plan]}
              {form.values.country ? ` · ${form.values.country}` : ''}.
            </Alert>
          )}
          {save.isError && (
            <Alert color="red" mt="md">
              {save.error instanceof ApiError ? save.error.message : 'Save failed'}
            </Alert>
          )}

          {canEditPlans && (
            <Group mt="lg">
              <Button type="submit" loading={save.isPending}>
                {editing ? 'Update pricing' : 'Save pricing'}
              </Button>
              {editing && (
                <Button type="button" variant="default" onClick={startNew}>
                  Cancel
                </Button>
              )}
            </Group>
          )}
          {!canEditPlans && (
            <Text size="xs" c="dimmed" mt="lg">
              View only — plan pricing can only be changed by an admin.
            </Text>
          )}
        </form>
      </Paper>

      <SubscriptionQueue />
    </Stack>
  )
}
