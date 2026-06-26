import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true, // 0.0.0.0: accesible desde fuera del contenedor (docker dev)
    port: 5173,
    // El código se monta por bind-mount en el contenedor; el polling asegura que
    // el watcher detecte los cambios del host (algunos FS no propagan inotify).
    watch: { usePolling: true },
  },
})
