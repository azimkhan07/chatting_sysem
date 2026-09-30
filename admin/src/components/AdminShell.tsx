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
  IconUserCog,
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
  { to: '/settings', label: 'Settings', icon: IconUserCog },
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
      <RouterNavLink key={n.to} to={n.to} style={{ textDecoration: 'none' }}>
        {({ isActive }) => (
          <NavLink
            component="span"
            active={isActive}
            label={n.label}
            leftSection={<Icon size={16} stroke={1.6} />}
            c="gray.3"
            styles={{
              root: {
                borderRadius: 8,
                marginBottom: 2,
                '&[data-active]': {
                  background: 'var(--mantine-primary-color-filled)',
                  color: 'white',
                },
                '&:not([data-active]):hover': {
                  background: 'var(--mantine-color-dark-7)',
                  color: 'white',
                },
              },
              label: { fontSize: 12.5, fontWeight: 500 },
            }}
          />
        )}
      </RouterNavLink>
    )
  })

  return (
    <AppShell
      header={{ height: 48 }}
      navbar={{ width: 220, breakpoint: 'sm', collapsed: { mobile: !opened } }}
      padding="sm"
    >
      <AppShell.Header h={48}>
        <Group h="100%" gap={0} wrap="nowrap">
          <Box
            visibleFrom="sm"
            h="100%"
            w={220}
            style={{
              background: 'var(--mantine-color-dark-8)',
              borderRight: '1px solid var(--mantine-color-dark-7)',
              display: 'flex',
              alignItems: 'center',
            }}
            px="sm"
          >
            <Text fw={700} c="white" size="md">
              amteCHAT&nbsp;
              <Text span c="dark.2" fw={400}>
                Admin
              </Text>
            </Text>
          </Box>
          <Group h="100%" justify="space-between" gap="sm" px="sm" flex={1}>
            <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
            <Box style={{ flex: 1 }} />
            <Group gap="sm">
              {admin && (
                <Text size="xs" c="dimmed">
                  {admin.display_name || admin.username}
                </Text>
              )}
              <Button
                variant="light"
                size="compact-sm"
                onClick={() => {
                  sessionStorage.removeItem('amtechat.admin.session-alert')
                  void logout()
                  navigate('/login')
                }}
              >
                Sign out
              </Button>
            </Group>
          </Group>
        </Group>
      </AppShell.Header>
      <AppShell.Navbar
        p="xs"
        styles={{ navbar: { background: 'var(--mantine-color-dark-8)' } }}
      >
        {links}
        <Box mt="auto" pt="sm">
          <Text size="xs" c="dimmed" px="sm">
            Admin console · Web only
          </Text>
        </Box>
      </AppShell.Navbar>
      <AppShell.Main bg="gray.1">
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