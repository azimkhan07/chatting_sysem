import {
  Alert,
  AppShell,
  Box,
  Burger,
  Button,
  Group,
  NavLink,
  Text,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconCalendarStats,
  IconMail,
  IconSettings,
  IconTicket,
  IconUsers,
  IconWallet,
} from '@tabler/icons-react'
import { useState } from 'react'
import { NavLink as RouterNavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAdminStore } from '../stores/session'

const nav = [
  { to: '/dashboard', label: 'Dashboard', icon: IconCalendarStats },
  { to: '/users', label: 'Users', icon: IconUsers },
  { to: '/subscriptions', label: 'Subscriptions', icon: IconWallet },
  { to: '/support', label: 'Support', icon: IconTicket },
  { to: '/email', label: 'Email', icon: IconMail },
  { to: '/gateways', label: 'Gateways', icon: IconSettings },
]

export default function AppShellLayout() {
  const [opened, { toggle }] = useDisclosure()
  const [sessionAlert, setSessionAlert] = useState(
    () => sessionStorage.getItem('amtechat.admin.session-alert') === '1',
  )
  const admin = useAdminStore((s) => s.admin)
  const logout = useAdminStore((s) => s.logout)
  const navigate = useNavigate()

  const links = nav.map((n) => {
    const Icon = n.icon
    return (
      <NavLink
        key={n.to}
        component={RouterNavLink}
        to={n.to}
        label={n.label}
        leftSection={<Icon size={18} stroke={1.6} />}
      />
    )
  })

  return (
    <AppShell
      header={{ height: 56 }}
      navbar={{ width: 240, breakpoint: 'sm', collapsed: { mobile: !opened } }}
      padding="md"
    >
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between">
          <Group>
            <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
            <Text fw={700} c="blue">
              amteCHAT&nbsp;<Text span c="dimmed" fw={400}>Admin</Text>
            </Text>
          </Group>
          <Group>
            {admin && (
              <Text size="sm" c="dimmed">
                {admin.display_name || admin.username}
              </Text>
            )}
            <Button
              variant="light"
              size="compact-sm"
              onClick={() => {
                logout()
                navigate('/login')
              }}
            >
              Sign out
            </Button>
          </Group>
        </Group>
      </AppShell.Header>
      <AppShell.Navbar p="sm">
        {links}
        <Box mt="auto" pt="sm">
          <Text size="xs" c="dimmed" px="sm">
            Admin console · Web only
          </Text>
        </Box>
      </AppShell.Navbar>
      <AppShell.Main>
        {sessionAlert && (
          <Alert
            color="yellow"
            title="Session refreshed"
            withCloseButton
            closeButtonLabel="Dismiss"
            mb="md"
            onClose={() => {
              sessionStorage.removeItem('amtechat.admin.session-alert')
              setSessionAlert(false)
            }}
          >
            This session replaced an older login on another device.
          </Alert>
        )}
        <Outlet />
      </AppShell.Main>
    </AppShell>
  )
}