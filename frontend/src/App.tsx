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
import JoinChat from '@/pages/JoinChat'
import Login from '@/pages/Login'
import Notifications from '@/pages/Notifications'
import Profile from '@/pages/Profile'
import Register from '@/pages/Register'
import ResetPassword from '@/pages/ResetPassword'
import Settings from '@/pages/Settings'
import UserProfile from '@/pages/UserProfile'
import Verify from '@/pages/Verify'
import AdminDashboard from '@/pages/admin/AdminDashboard'
import AdminLayout from '@/pages/admin/AdminLayout'
import AdminLogin from '@/pages/admin/AdminLogin'
import AdminReviews from '@/pages/admin/AdminReviews'

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
              <Route
                path={`${path('chat')}/join/:code`}
                element={<JoinChat />}
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
          <Route path="/admin/login" element={<AdminLogin />} />
          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<AdminDashboard />} />
            <Route path="reviews" element={<AdminReviews />} />
          </Route>
          <Route path="*" element={<Navigate to={path('home')} replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  )
}