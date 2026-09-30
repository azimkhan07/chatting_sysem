import {
  Alert,
  Button,
  Divider,
  Group,
  Loader,
  Modal,
  Paper,
  SegmentedControl,
  Stack,
  Table,
  Text,
  TextInput,
} from '@mantine/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { RichTextEditor } from '../components/RichTextEditor'
import { adminApi, ApiError } from '../lib/api'

interface EmailTemplate {
  id: number
  title: string
  subject: string
  html_body: string
  created_at: string
}

interface EmailConfig {
  host: string
  port: number
  username: string
  password: string
  encryption: string
  from_email: string
  from_name: string
  enabled: boolean
}

interface TemplateForm {
  title: string
  subject: string
  html_body: string
}

const EMPTY: TemplateForm = { title: '', subject: '', html_body: '' }
const EMPTY_CONFIG: EmailConfig = {
  host: '',
  port: 587,
  username: '',
  password: '',
  encryption: 'tls',
  from_email: '',
  from_name: '',
  enabled: false,
}

export default function Email() {
  const qc = useQueryClient()
  const [section, setSection] = useState<'templates' | 'config'>('templates')
  const [mode, setMode] = useState<'none' | 'create' | 'edit'>('none')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<TemplateForm>(EMPTY)
  const [error, setError] = useState<string | null>(null)

  const templatesQuery = useQuery({
    queryKey: ['admin', 'email-templates'],
    queryFn: () =>
      adminApi.get<{ templates: EmailTemplate[] }>('/admin/email/templates'),
  })

  const configQuery = useQuery({
    queryKey: ['admin', 'email-config'],
    queryFn: () => adminApi.get<{ config: EmailConfig }>('/admin/email/config'),
  })

  const save = useMutation({
    mutationFn: () =>
      mode === 'edit' && editingId !== null
        ? adminApi.patch<{ template: EmailTemplate }>(
            `/admin/email/templates/${editingId}`,
            form,
          )
        : adminApi.post<{ template: EmailTemplate }>('/admin/email/templates', form),
    onSuccess: () => {
      setMode('none')
      setForm(EMPTY)
      void qc.invalidateQueries({ queryKey: ['admin', 'email-templates'] })
    },
  })

  const [cfgDraft, setCfgDraft] = useState<EmailConfig | null>(null)
  const cfg = cfgDraft ?? configQuery.data?.config ?? EMPTY_CONFIG
  const setCfg = (patch: Partial<EmailConfig>) =>
    setCfgDraft({ ...cfg, ...patch })

  const saveConfig = useMutation({
    mutationFn: (value: EmailConfig) =>
      adminApi.put<{ config: EmailConfig }>('/admin/email/config', value),
    onSuccess: () => {
      setCfgDraft(null)
      void qc.invalidateQueries({ queryKey: ['admin', 'email-config'] })
    },
  })

  const close = () => {
    setMode('none')
    setForm(EMPTY)
  }

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Text fz="lg" fw={700}>
          Email
        </Text>
      </Group>

      <Paper withBorder p="xs" w="fit-content">
        <SegmentedControl
          size="xs"
          value={section}
          onChange={(v) => setSection(v as 'templates' | 'config')}
          data={[
            { value: 'templates', label: 'Email templates' },
            { value: 'config', label: 'Email config' },
          ]}
        />
      </Paper>

      {section === 'templates' && (
        <>
          <Group justify="space-between">
            <Text fz="sm" c="dimmed">
              Templates are picked by title when a reply or broadcast is sent.
            </Text>
            <Button
              onClick={() => {
                setMode('create')
                setForm(EMPTY)
              }}
            >
              New template
            </Button>
          </Group>

          {templatesQuery.isError && (
            <Alert color="red">
              {templatesQuery.error instanceof Error
                ? templatesQuery.error.message
                : 'Could not load email templates.'}
            </Alert>
          )}
          {templatesQuery.isPending && <Loader mx="auto" my="xl" />}

          {!templatesQuery.isPending && (
            <Paper withBorder p="md" bg="white">
              <Table.ScrollContainer minWidth={640}>
                <Table highlightOnHover>
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>Title</Table.Th>
                      <Table.Th>Subject</Table.Th>
                      <Table.Th>Updated</Table.Th>
                      <Table.Th />
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {(templatesQuery.data?.templates ?? []).map((t) => (
                      <Table.Tr key={t.id}>
                        <Table.Td>
                          <Text fw={600}>{t.title}</Text>
                        </Table.Td>
                        <Table.Td>{t.subject}</Table.Td>
                        <Table.Td>
                          <Text size="sm" c="dimmed">
                            {new Date(t.created_at).toLocaleDateString()}
                          </Text>
                        </Table.Td>
                        <Table.Td>
                          <Group gap="xs">
                            <Button
                              size="compact-sm"
                              variant="light"
                              onClick={() => {
                                setMode('edit')
                                setEditingId(t.id)
                                setForm({
                                  title: t.title,
                                  subject: t.subject,
                                  html_body: t.html_body,
                                })
                              }}
                            >
                              Edit
                            </Button>
                            <Button
                              size="compact-sm"
                              variant="subtle"
                              color="red"
                              onClick={() => {
                                void adminApi
                                  .delete(`/admin/email/templates/${t.id}`)
                                  .then(() =>
                                    qc.invalidateQueries({
                                      queryKey: ['admin', 'email-templates'],
                                    }),
                                  )
                              }}
                            >
                              Delete
                            </Button>
                          </Group>
                        </Table.Td>
                      </Table.Tr>
                    ))}
                    {(templatesQuery.data?.templates ?? []).length === 0 && (
                      <Table.Tr>
                        <Table.Td colSpan={4}>
                          <Text c="dimmed" ta="center" py="md">
                            No templates yet — create one to get started.
                          </Text>
                        </Table.Td>
                      </Table.Tr>
                    )}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            </Paper>
          )}
        </>
      )}

      {section === 'config' && (
        <Paper withBorder p="md" bg="white" maw={640}>
          <Text fw={600} mb="xs">
            SMTP transport
          </Text>
          {configQuery.isError && (
            <Alert color="red" mb="md">
              {configQuery.error instanceof Error
                ? configQuery.error.message
                : 'Could not load email config.'}
            </Alert>
          )}
          <Stack>
            <Group grow align="flex-end">
              <TextInput
                label="SMTP host"
                placeholder="smtp.example.com"
                value={cfg.host}
                onChange={(e) => setCfg({ host: e.currentTarget.value })}
              />
              <TextInput
                label="Port"
                type="number"
                value={String(cfg.port)}
                onChange={(e) => setCfg({ port: Number(e.currentTarget.value) || 587 })}
              />
            </Group>
            <Group grow>
              <TextInput
                label="Username"
                value={cfg.username}
                onChange={(e) => setCfg({ username: e.currentTarget.value })}
              />
              <TextInput
                label="Password / API key"
                value={cfg.password}
                onChange={(e) => setCfg({ password: e.currentTarget.value })}
              />
            </Group>
            <Group grow>
              <TextInput
                label="From email"
                placeholder="no-reply@amtechat.com"
                value={cfg.from_email}
                onChange={(e) => setCfg({ from_email: e.currentTarget.value })}
              />
              <TextInput
                label="From name"
                placeholder="amteCHAT"
                value={cfg.from_name}
                onChange={(e) => setCfg({ from_name: e.currentTarget.value })}
              />
            </Group>
            <Group>
              <select
                value={cfg.encryption}
                onChange={(e) => setCfg({ encryption: e.currentTarget.value })}
                style={{
                  fontFamily: 'inherit',
                  fontSize: 12.5,
                  padding: '4px 8px',
                  borderRadius: 6,
                  border: '1px solid var(--mantine-color-default-border)',
                  background: 'white',
                }}
              >
                <option value="none">None</option>
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
              </select>
              <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12.5 }}>
                <input
                  type="checkbox"
                  checked={cfg.enabled}
                  onChange={(e) => setCfg({ enabled: e.currentTarget.checked })}
                />{' '}
                Sending enabled
              </label>
              <Button
                variant="light"
                size="compact-sm"
                onClick={() => saveConfig.mutate(cfg)}
                loading={saveConfig.isPending}
              >
                Save config
              </Button>
            </Group>
            <Divider />
            <Text size="xs" c="dimmed">
              Get this right once — support replies and broadcast emails read these
              values on every send, so changing a mail server never needs a redeploy.
            </Text>
          </Stack>
        </Paper>
      )}

      <Modal
        opened={mode !== 'none'}
        onClose={close}
        title={mode === 'edit' ? 'Edit template' : 'New template'}
        size="xl"
      >
        <Stack>
          <Group grow>
            <TextInput
              label="Title"
              value={form.title}
              onChange={(e) => setForm((f) => ({ ...f, title: e.currentTarget.value }))}
            />
            <TextInput
              label="Subject"
              value={form.subject}
              onChange={(e) => setForm((f) => ({ ...f, subject: e.currentTarget.value }))}
            />
          </Group>
          <div style={{ fontSize: 12.5 }}>
            <Text fw={600} mb={6}>
              Email body
            </Text>
            <RichTextEditor
              value={form.html_body}
              onChange={(html) => setForm((f) => ({ ...f, html_body: html }))}
            />
          </div>
          {error && <Alert color="red">{error}</Alert>}
          <Group justify="flex-end">
            <Button
              onClick={() =>
                save.mutate(undefined, {
                  onError: (e) =>
                    setError(e instanceof ApiError ? e.message : 'Save failed'),
                })
              }
              loading={save.isPending}
            >
              Save template
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  )
}