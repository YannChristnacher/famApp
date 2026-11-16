import { reactive } from 'vue'
import type { SharedUser } from '@/types/auth'

interface TestPageProps {
  auth: { user: SharedUser | null }
  status: string | null
  [key: string]: unknown
}

const defaultProps = (): TestPageProps => ({ auth: { user: null }, status: null })

/**
 * Static Inertia page returned by the mocked usePage() in unit tests.
 * Tests may change `url` to simulate an Inertia visit, or the shared props
 * (`auth.user`, `status`) to simulate a signed-in user or a flash message.
 */
export const testPage = reactive({ component: 'Test', url: '/', props: defaultProps(), version: null })

export function resetTestPage(): void {
  testPage.url = '/'
  testPage.props = defaultProps()
}

export const testUser: SharedUser = { id: 1, name: 'Marie Dupont', email: 'marie@example.com', role: 'user' }
