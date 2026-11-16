import { vi } from 'vitest'
import { defineComponent, reactive } from 'vue'

// Inertia's <Head> needs a live Inertia app; in unit tests it renders nothing.
// usePage() is backed by a static page so Nuxt UI's Inertia links can render.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
  Head: defineComponent({ name: 'InertiaHead', render: () => null }),
  usePage: () => testPage,
}))

const testPage = reactive({ component: 'Test', url: '/', props: {}, version: null })

// Icons are fetched from the Iconify API at runtime; tests stay offline.
vi.mock('@iconify/vue', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@iconify/vue')>()),
  iconLoaded: () => true,
  Icon: (await import('./stubs/IconifyIconStub')).default,
}))

// Named routes normally injected by the @routes Blade directive (Ziggy).
globalThis.Ziggy = {
  url: 'http://localhost',
  port: null,
  defaults: {},
  routes: {
    home: { uri: '/', methods: ['GET', 'HEAD'] },
  },
}
