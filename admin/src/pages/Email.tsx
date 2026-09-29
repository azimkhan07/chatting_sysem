import {
  Alert,
  Button,
  Group,
  Loader,
  Modal,
  Paper,
  Stack,
  Table,
  Text,
  TextInput,
} from '@mantine/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { adminApi, ApiError } from '../lib/api'

interface EmailTemplate {
  id: number
  title: string
  subject: string
  html_body: string
  created_at: string
}

interface TemplateForm {
  title: string
  subject: string
  html_body: string
}

const EMPTY: TemplateForm = { title: '', subject: '', html_body: '' }

export default function Email() {
  const qc = useQueryClient()
  const [mode, setMode] = useState<'none' | 'create' | 'edit'>('none')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<TemplateForm>(EMPTY)
  const [error, setError] = useState<string | null>(null)

  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'email-templates'],
    queryFn: () =>
      adminApi.get<{ templates: EmailTemplate[] }>('/admin/email/templates'),
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

  const close = () => {
    setMode('none')
    setForm(EMPTY)
  }

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Text fz="lg" fw={700}>
          Email templates
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

      {isError && <Alert color="red">Could not load email templates.</Alert>}
      {isPending && <Loader mx="auto" my="xl" />}

      {!isPending && (
        <Paper withBorder p="md">
          <Table.ScrollContainer minWidth={640}>
            <Table highlightOnHover verticalSpacing="sm">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Title</Table.Th>
                  <Table.Th>Subject</Table.Th>
                  <Table.Th>Updated</Table.Th>
                  <Table.Th />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {(data?.templates ?? []).map((t) => (
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
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Paper>
      )}

      <Modal
        opened={mode !== 'none'}
        onClose={close}
        title={mode === 'edit' ? 'Edit template' : 'New template'}
        size="lg"
      >
        <Stack>
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
          <TextInput
            label="HTML body"
            value={form.html_body}
            onChange={(e) => setForm((f) => ({ ...f, html_body: e.currentTarget.value }))}
            placeholder="<h1>Hello {{user.name}}</h1> …"
          />
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