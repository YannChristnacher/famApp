import { vi } from 'vitest'
import { defineComponent } from 'vue'

// Inertia's <Head> needs a live Inertia app; in unit tests it renders nothing.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
  Head: defineComponent({ name: 'InertiaHead', render: () => null }),
}))
