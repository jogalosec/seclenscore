import { defineConfig }  from 'vite'
import vue               from '@vitejs/plugin-vue'
import AutoImport        from 'unplugin-auto-import/vite'
import Components        from 'unplugin-vue-components/vite'
import { PrimeVueResolver } from '@primevue/auto-import-resolver'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    vue(),
    AutoImport({ imports: ['vue', 'vue-router', 'pinia'], dts: false }),
    Components({ resolvers: [PrimeVueResolver()], dts: false }),
  ],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    proxy: {
      '/api':  { target: 'http://localhost:8000', changeOrigin: true },
      '/auth': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
  build: {
    outDir: 'dist',
    rollupOptions: {
      output: {
        manualChunks: {
          'vue-core': ['vue', 'vue-router', 'pinia'],
          'primevue': ['primevue', 'primeicons'],
          'axios':    ['axios'],
        },
      },
    },
  },
})
