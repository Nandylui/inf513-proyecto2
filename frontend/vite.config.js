import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// Se publica dentro de backend/public para que Laravel (API) y React (SPA) vivan bajo la misma URL:
//   https://www.tecnoweb.org.bo/inf513/grupo07sa/proyecto2/        -> aplicación React
//   https://www.tecnoweb.org.bo/inf513/grupo07sa/proyecto2/api/... -> API RESTful de Laravel
// base './' hace que los recursos sean relativos: funciona en cualquier subdirectorio del servidor.
export default defineConfig({
  base: './',
  plugins: [react(), tailwindcss()],
  build: {
    outDir: '../backend/public',
    emptyOutDir: false, // conserva index.php, .htaccess, etc. de Laravel
    chunkSizeWarningLimit: 900,
  },
  server: {
    port: 5173,
    proxy: { '/api': { target: process.env.VITE_PROXY || 'http://127.0.0.1:8000', changeOrigin: true } },
  },
  test: { environment: 'node', include: ['src/**/*.test.js'] },
})
