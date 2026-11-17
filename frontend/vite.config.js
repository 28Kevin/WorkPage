import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  // Con API_PROXY_TARGET el servidor de desarrollo reenvia /api a ese backend
  // (por ejemplo produccion), sin depender de que su CORS acepte localhost.
  const proxyTarget = loadEnv(mode, process.cwd(), '').API_PROXY_TARGET

  return {
    plugins: [vue(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      strictPort: true,
      watch: {
        // Necesario para que el hot reload funcione dentro del contenedor.
        usePolling: true,
      },
      proxy: proxyTarget
        ? { '/api': { target: proxyTarget, changeOrigin: true, secure: true } }
        : undefined,
    },
  }
})
