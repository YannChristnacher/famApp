import { describe, expect, it, vi } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import DashboardLayout from './DashboardLayout.vue'

vi.mock('@/navigation/registry', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/navigation/registry')>()

  return {
    ...original,
    navigationRegistry: [
      ...original.navigationRegistry,
      { label: 'Administration', icon: 'i-lucide-house', route: 'home', requiredRole: 'admin' },
    ],
  }
})

async function menuLabels() {
  const wrapper = await mountWithUi(DashboardLayout, { props: { title: 'Accueil', breadcrumb: [] } })

  return wrapper.findComponent({ name: 'NavigationMenu' }).props('items').map((item: { label: string }) => item.label)
}

describe('DashboardLayout navigation filtered by the shared role', () => {
  it('hides admin entries from a regular user', async () => {
    testPage.props.auth.user = { ...testUser, role: 'user' }

    expect(await menuLabels()).toEqual(['Accueil', 'Bibliothèque'])
  })

  it('shows admin entries to an admin', async () => {
    testPage.props.auth.user = { ...testUser, role: 'admin' }

    expect(await menuLabels()).toEqual(['Accueil', 'Bibliothèque', 'Demandes de compte', 'Administration'])
  })

  it('shows the user menu in the sidebar footer', async () => {
    testPage.props.auth.user = testUser
    const wrapper = await mountWithUi(DashboardLayout, { props: { title: 'Accueil', breadcrumb: [] } })

    const sidebar = wrapper.findComponent({ name: 'DashboardSidebar' })
    expect(sidebar.findComponent({ name: 'UserMenu' }).exists()).toBe(true)
    expect(sidebar.text()).toContain('Marie Dupont')
  })
})
