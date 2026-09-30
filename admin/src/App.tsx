import { Navigate, Route, Routes } from 'react-router-dom'
import AdminShell from './components/AdminShell'
import Dashboard from './pages/Dashboard'
import Email from './pages/Email'
import Gateways from './pages/Gateways'
import Login from './pages/Login'
import Settings from './pages/Settings'
import Subscriptions from './pages/Subscriptions'
import Support from './pages/Support'
import Users from './pages/Users'
import { useAdminStore } from './stores/session'

function RequireAuth({ children }: { children: React.ReactElement }) {
  const token = useAdminStore((s) => s.token)
  if (!token) return <Navigate to="/login" replace />
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
        <Route path="/support" element={<Support />} />
        <Route path="/email" element={<Email />} />
        <Route path="/gateways" element={<Gateways />} />
        <Route path="/settings" element={<Settings />} />
      </Route>
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}