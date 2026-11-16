import { reactive } from 'vue'

/**
 * Static Inertia page returned by the mocked usePage() in unit tests.
 * Tests may change `url` to simulate an Inertia visit.
 */
export const testPage = reactive({ component: 'Test', url: '/', props: {}, version: null })
