import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import ui from '@nuxt/ui/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    vue(),
    ui({ router: 'inertia', icon: { clientBundle: { scan: true } } }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  test: {
    environment: 'happy-dom',
    include: ['resources/js/**/*.test.ts'],
    setupFiles: ['resources/js/test/setup.ts'],
    server: {
      deps: {
        inline: ['@nuxt/ui'],
      },
    },
  },
})
