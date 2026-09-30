import {
  Alert,
  Button,
  Center,
  Paper,
  PasswordInput,
  Stack,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { IconShieldLock } from '@tabler/icons-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiError } from '../lib/api'
import { useAdminStore } from '../stores/session'

export default function Login() {
  const login = useAdminStore((s) => s.login)
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const form = useForm({
    initialValues: { identifier: '', password: '' },
    validate: {
      identifier: (v) => (!v ? 'Username or email required' : null),
      password: (v) => (v.length < 6 ? 'Password must be at least 6 characters' : null),
    },
  })

  const submit = form.onSubmit(async (values) => {
    setBusy(true)
    setError(null)
    try {
      const { superseded } = await login(values.identifier, values.password)
      if (superseded) {
        sessionStorage.setItem('amtechat.admin.session-alert', '1')
      }
      navigate('/dashboard', { replace: true })
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Unable to sign in.')
    } finally {
      setBusy(false)
    }
  })

  return (
    <Center h="100vh" bg="gray.1">
      <Paper w="min(420px, 92vw)" p="xl" radius="lg" shadow="md" withBorder>
        <Stack>
          <Center>
            <IconShieldLock size={34} color="var(--mantine-color-blue-6)" />
          </Center>
          <Title order={3} ta="center">
            amteCHAT Admin
          </Title>
          <Text c="dimmed" size="sm" ta="center">
            Admin & support console · web only
          </Text>
          {error && <Alert color="red">{error}</Alert>}
          <form onSubmit={submit}>
            <Stack>
              <TextInput
                label="Username or email"
                placeholder="admin@amtechat.com"
                {...form.getInputProps('identifier')}
              />
              <PasswordInput
                label="Password"
                placeholder="Your password"
                {...form.getInputProps('password')}
              />
              <Button type="submit" loading={busy} fullWidth>
                Sign in
              </Button>
            </Stack>
          </form>
        </Stack>
      </Paper>
    </Center>
  )
}