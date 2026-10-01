import { Navigate, Route, Routes } from 'react-router-dom'
import AdminShell from './components/AdminShell'
import Dashboard from './pages/Dashboard'
import Email from './pages/Email'
import Gateways from './pages/Gateways'
import Login from './pages/Login'
import Reports from './pages/Reports'
import Settings from './pages/Settings'
import Subscriptions from './pages/Subscriptions'
import Support from './pages/Support'
import Users from './pages/Users'
import { useCan, type Capability } from './lib/permissions'
import { useAdminStore } from './stores/session'

function RequireAuth({ children }: { children: React.ReactElement }) {
  const token = useAdminStore((s) => s.token)
  if (!token) return <Navigate to="/login" replace />
  return children
}

/**
 * Hides a screen from a role the API would refuse anyway.
 *
 * Hiding the nav link is not enough on its own: the address bar still reaches
 * the route, a bookmark still works, and the page would mount, fire its queries
 * and render a row of error boxes. This turns that into a redirect before the
 * page ever mounts.
 *
 * It is a usability guard, not a security control. The API is the real gate, and
 * a person who edits this file into existence gets 403s.
 */
function RequireCapability({
  capability,
  children,
}: {
  capability: Capability
  children: React.ReactElement
}) {
  const can = useCan()
  if (!can(capability)) return <Navigate to="/dashboard" replace />
  return children
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <RequireAuth>
            <AdminShell />
          </RequireAuth>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/users" element={<Users />} />
        <Route path="/subscriptions" element={<Subscriptions />} />
        <Route path="/reports" element={<Reports />} />
        <Route path="/support" element={<Support />} />
        <Route
          path="/email"
          element={
            <RequireCapability capability="viewConfig">
              <Email />
            </RequireCapability>
          }
        />
        <Route
          path="/gateways"
          element={
            <RequireCapability capability="viewConfig">
              <Gateways />
            </RequireCapability>
          }
        />
        <Route
          path="/settings"
          element={
            // Not capability-gated: every staff role needs the change-password
            // form on this page. The team roster within it is gated by the page.
            <Settings />
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}