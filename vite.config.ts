import { defineConfig } from 'vite'
import type { Plugin } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import ui from '@nuxt/ui/vite'
import { fileURLToPath, URL } from 'node:url'

/**
 * zxing-wasm falls back to jsDelivr for its WebAssembly file. The app always
 * serves it from the build (`locateFile` in support/library/barcodeDetector.ts);
 * this also removes the fallback URL, so that no CDN can be reached even if the
 * override were lost. The build fails if the library no longer has this URL
 * (scripts/check-build-no-cdn.mjs then checks the whole build).
 */
function zxingWithoutCdnFallback(): Plugin {
  const module = /[\\/]barcode-detector[\\/]dist[\\/]es[\\/]zxing-exported\.js$/
  const cdnFallback = /`https:\/\/fastly\.jsdelivr\.net\/npm\/zxing-wasm@[^`]*`/g

  return {
    name: 'famapp:zxing-without-cdn-fallback',
    transform(code, id) {
      if (!module.test(id)) {
        return null
      }

      const patched = code.replace(cdnFallback, "''")
      if (patched === code) {
        this.error('The jsDelivr fallback of zxing-wasm was not found: check how barcode-detector locates its WebAssembly file.')
      }

      return { code: patched, map: null }
    },
  }
}

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
    zxingWithoutCdnFallback(),
    ui({
      router: 'inertia',
      icon: { clientBundle: { scan: true } },
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
