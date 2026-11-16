import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import ui from '@nuxt/ui/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.ts'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    tailwindcss(),
    ui({
      router: 'inertia',
      ui: {
        colors: {
          primary: 'emerald',
          neutral: 'zinc',
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  server: {
    watch: {
      ignored: ['**/storage/framework/views/**'],
    },
  },
})
