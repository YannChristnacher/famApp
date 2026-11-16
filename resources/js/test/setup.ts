import { afterEach, vi } from 'vitest'
import { defineComponent } from 'vue'
import { enableAutoUnmount } from '@vue/test-utils'

enableAutoUnmount(afterEach)

afterEach(async () => {
  const { resetTestPage } = await import('./testPage')
  resetTestPage()
  vi.restoreAllMocks()
})

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
    logout: { uri: 'logout', methods: ['POST'] },
    login: { uri: 'login', methods: ['GET', 'HEAD'] },
    'login.store': { uri: 'login', methods: ['POST'] },
    'password.request': { uri: 'forgot-password', methods: ['GET', 'HEAD'] },
    'password.email': { uri: 'forgot-password', methods: ['POST'] },
    'password.update': { uri: 'reset-password', methods: ['POST'] },
    'account-request.create': { uri: 'account-request', methods: ['GET', 'HEAD'] },
    'account-request.store': { uri: 'account-request', methods: ['POST'] },
  },
}
