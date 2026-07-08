import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { readFileSync } from 'node:fs'

// Versión (desde package.json): se inyecta en el bundle para mostrarla en el footer.
// Bumpeá la versión al hacer cambios para notar en el footer que subió una versión nueva.
const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf-8')) as { version: string }

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  define: {
    __APP_VERSION__: JSON.stringify(pkg.version),
  },
  server: {
    host: true, // 0.0.0.0: accesible desde fuera del contenedor (docker dev)
    port: 5173,
    // El código se monta por bind-mount en el contenedor; el polling asegura que
    // el watcher detecte los cambios del host (algunos FS no propagan inotify).
    watch: { usePolling: true },
  },
})
