import { QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'

import ProtectedRoute from '@/components/ProtectedRoute'
import { queryClient } from '@/lib/queryClient'
import { path } from '@/lib/paths'
import Home from '@/pages/Home'
import Login from '@/pages/Login'
import PlaceholderPage from '@/pages/PlaceholderPage'
import Register from '@/pages/Register'

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route element={<ProtectedRoute />}>
            <Route path={path('home')} element={<Home />} />
            <Route
              path={path('chat')}
              element={
                <PlaceholderPage
                  title="Chats"
                  caption="Your conversations will live here — DMs and groups."
                />
              }
            />
            <Route
              path={path('explore')}
              element={
                <PlaceholderPage
                  title="Explore"
                  caption="Discovery, trending topics and new people."
                  rows={12}
                />
              }
            />
            <Route
              path={path('notifications')}
              element={
                <PlaceholderPage
                  title="Activity"
                  caption="Likes, follows, comments and mentions."
                  rows={6}
                />
              }
            />
            <Route
              path={path('settings')}
              element={
                <PlaceholderPage
                  title="Settings"
                  caption="Account, privacy and the blue tick — coming soon."
                  rows={4}
                />
              }
            />
          </Route>
          <Route path="/admin" element={<AdminSkeleton />} />
          <Route path="*" element={<Navigate to={path('home')} replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  )
}

function AdminSkeleton() {
  return (
    <div className="grid h-svh place-items-center bg-midnight-950 px-4 text-center">
      <div className="max-w-sm rounded-3xl border border-white/10 bg-slate-900/60 p-8">
        <p className="text-lg font-extrabold tracking-tight text-white">
          Admin surface reserved
        </p>
        <p className="mt-2 text-sm text-slate-400">
          The admin panel is scoped out per the security & route strategy — it
          opens in a dedicated phase with its own auth perimeter.
        </p>
      </div>
    </div>
  )
}