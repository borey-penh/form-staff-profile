import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import fs from 'node:fs'
import path from 'node:path'

// Vue SPA in frontend/src, built into backend/public for `php artisan serve`.
//
// Dev mode (npm run dev):
//   - http://127.0.0.1:8000  → Laravel serves the page, assets + HMR come from :5173
//   - http://127.0.0.1:5173  → the dev shell plugin below serves the HTML directly
//     (laravel-vite-plugin normally 404s /index.html, this bypasses that)
//   - /api and /storage are proxied to Laravel
export default defineConfig({
  plugins: [
    laravel({
      input: ['src/main.js'],
      publicDirectory: '../backend/public',
      hotFile: '../backend/public/hot',
      refresh: false,
    }),
    vue(),
    {
      name: 'dev-html-shell',
      apply: 'serve',
      configureServer(server) {
        server.middlewares.use(async (req, res, next) => {
          const url = (req.url ?? '/').split('?')[0]
          const wantsHtml =
            (url === '/' || url === '/index.html' || !url.includes('.')) &&
            (req.headers.accept ?? '').includes('text/html')

          if (!wantsHtml) return next()

          try {
            const file = url.startsWith('/index.html')
              ? url.slice(1)
              : 'index.html'
            const htmlPath = path.resolve(__dirname, file)
            if (!fs.existsSync(htmlPath)) return next()

            const html = await server.transformIndexHtml(url, fs.readFileSync(htmlPath, 'utf-8'))
            res.statusCode = 200
            res.setHeader('Content-Type', 'text/html')
            res.end(html)
          } catch (e) {
            next(e)
          }
        })
      },
    },
  ],
  resolve: {
    alias: {
      '@': '/src',
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': 'http://127.0.0.1:8000',
      '/storage': 'http://127.0.0.1:8000',
    },
  },
  build: {
    emptyOutDir: true,
  },
})
