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
  IconFlag,
  IconMail,
  IconSettings,
  IconTicket,
  IconUsers,
  IconUserCog,
  IconWallet,
} from '@tabler/icons-react'
import { useState } from 'react'
import { NavLink as RouterNavLink, Outlet, useNavigate } from 'react-router-dom'
import { useCan, type Capability } from '../lib/permissions'
import { useAdminStore } from '../stores/session'

// `capability` is what decides whether the link is rendered. Leaving a link out
// is not a cosmetic choice: the API answers 403 for a role that has no business
// on the screen, so a link that is shown anyway takes the person to a dead page
// and an error box. Hiding it is also the honest signal - the screen is not
// theirs to use.
const nav: { to: string; label: string; icon: typeof IconCalendarStats; capability?: Capability }[] = [
  { to: '/dashboard', label: 'Dashboard', icon: IconCalendarStats },
  { to: '/users', label: 'Users', icon: IconUsers },
  { to: '/subscriptions', label: 'Subscriptions', icon: IconWallet },
  { to: '/reports', label: 'Reports', icon: IconFlag },
  { to: '/support', label: 'Support', icon: IconTicket },
  { to: '/email', label: 'Email', icon: IconMail, capability: 'viewConfig' },
  { to: '/gateways', label: 'Gateways', icon: IconSettings, capability: 'viewConfig' },
  // No capability: Settings opens for every staff role, because it carries the
  // change-your-own-password form and that is the one thing all of them need.
  // The team roster inside the page is gated on `viewStaff` by the page itself.
  // Gating the whole page on the roster would lock support and moderator out of
  // rotating their own credentials.
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
  const can = useCan()

  const links = nav
    // No capability means every staff role may open it.
    .filter((n) => n.capability === undefined || can(n.capability))
    .map((n) => {
      const Icon = n.icon
      return (
        <RouterNavLink key={n.to} to={n.to} style={{ textDecoration: 'none' }}>
          {({ isActive }) => (
            <NavLink
              component="span"
              className="admin-nav"
              active={isActive}
              label={n.label}
              leftSection={<Icon size={16} stroke={1.6} />}
              styles={{ label: { fontSize: 12.5, fontWeight: 500 } }}
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