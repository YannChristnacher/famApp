import { afterEach, vi } from 'vitest'
import { defineComponent } from 'vue'
import { enableAutoUnmount } from '@vue/test-utils'

enableAutoUnmount(afterEach)

// Inertia's <Head> needs a live Inertia app; in unit tests it renders nothing.
// usePage() returns a static, mutable page (see testPage.ts).
vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const { testPage } = await import('./testPage')

  return {
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: defineComponent({ name: 'InertiaHead', render: () => null }),
    usePage: () => testPage,
  }
})

// Named routes normally injected by the @routes Blade directive (Ziggy).
globalThis.Ziggy = {
  url: 'http://localhost',
  port: null,
  defaults: {},
  routes: {
    home: { uri: '/', methods: ['GET', 'HEAD'] },
  },
}
