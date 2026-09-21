import { QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'

import AppLayout from '@/components/AppLayout'
import ProtectedRoute from '@/components/ProtectedRoute'
import ThemeWash from '@/components/ThemeWash'
import { queryClient } from '@/lib/queryClient'
import { path } from '@/lib/paths'
import ChatPage from '@/pages/ChatPage'
import Explore from '@/pages/Explore'
import ForgotPassword from '@/pages/ForgotPassword'
import HashtagPage from '@/pages/HashtagPage'
import Home from '@/pages/Home'
import Login from '@/pages/Login'
import Notifications from '@/pages/Notifications'
import Profile from '@/pages/Profile'
import Register from '@/pages/Register'
import ResetPassword from '@/pages/ResetPassword'
import Settings from '@/pages/Settings'
import UserProfile from '@/pages/UserProfile'
import Verify from '@/pages/Verify'

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeWash />
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/forgot-password" element={<ForgotPassword />} />
          <Route path="/reset-password" element={<ResetPassword />} />
          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              <Route path={path('home')} element={<Home />} />
              <Route path={path('chat')} element={<ChatPage />} />
              <Route
                path={`${path('chat')}/:conversationId`}
                element={<ChatPage />}
              />
              <Route path={path('explore')} element={<Explore />} />
              <Route path="/hashtags/:tag" element={<HashtagPage />} />
              <Route
                path={path('notifications')}
                element={<Notifications />}
              />
              <Route path="/u/:username" element={<UserProfile />} />
              <Route
                path={path('profile')}
                element={<Profile />}
              />
              <Route
                path={path('settings')}
                element={<Settings />}
              />
              <Route path={path('verified')} element={<Verify />} />
            </Route>
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