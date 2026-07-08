import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { readFileSync } from 'node:fs'

// Versión (desde package.json) y sello de build: se inyectan en el bundle para mostrarlos
// en el footer. La fecha/hora de build cambia en cada `vite build` (cada deploy) → sirve de
// señal para notar que subió una versión nueva.
const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf-8')) as { version: string }
const buildDate = new Date().toISOString().slice(0, 16).replace('T', ' ')

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  define: {
    __APP_VERSION__: JSON.stringify(pkg.version),
    __BUILD_DATE__: JSON.stringify(buildDate),
  },
  server: {
    host: true, // 0.0.0.0: accesible desde fuera del contenedor (docker dev)
    port: 5173,
    // El código se monta por bind-mount en el contenedor; el polling asegura que
    // el watcher detecte los cambios del host (algunos FS no propagan inotify).
    watch: { usePolling: true },
  },
})
