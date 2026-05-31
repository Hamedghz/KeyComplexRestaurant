import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import { defineConfig } from 'vite'

const projectRoot = dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  base: '/dist/',
  server: { port: 5173 },
  build: {
    manifest: true,
    outDir: 'dist',
    emptyOutDir: true,
    cssCodeSplit: true,
    minify: 'esbuild',
    rollupOptions: {
      input: {
        app: resolve(projectRoot, 'assets/js/app.js')
      },
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]'
      }
    }
  }
})
