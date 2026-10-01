import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// The console API is a separate Laravel app (`admin-backend/`), not backend/.
// The paths are unchanged, so only the host moves - a dev talking to the wrong
// port gets a 404 from the main app, not a silently wrong response.
//
// Read through Vite's own loadEnv rather than process.env so the value can also
// come from an .env file next to the console, and so this file keeps compiling
// without pulling Node's ambient types into a project that has none.
export default defineConfig(({ mode }) => {
  // '.' rather than process.cwd(): loadEnv resolves a relative envDir against
  // the working directory itself, and naming it that way keeps this file free
  // of Node's ambient globals.
  const env = loadEnv(mode, '.', 'ADMIN_')
  const adminApi = env.ADMIN_API_URL || 'http://127.0.0.1:8001'

  return {
    plugins: [react()],
    server: {
      port: 5175,
      proxy: {
        '/api': adminApi,
      },
    },
  }
})
