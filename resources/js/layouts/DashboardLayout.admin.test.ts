import { describe, expect, it, vi } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import DashboardLayout from './DashboardLayout.vue'

async function mountLayout() {
  return mountWithUi(DashboardLayout, { props: { title: 'Accueil', breadcrumb: [] } })
}

function menuItems(wrapper: Awaited<ReturnType<typeof mountLayout>>) {
  return wrapper.findComponent({ name: 'NavigationMenu' }).props('items') as { label: string, to: string }[]
}

describe('DashboardLayout admin entry from the real registry', () => {
  it('shows the account requests entry to an admin', async () => {
    testPage.props.auth.user = { ...testUser, role: 'admin' }

    expect(menuItems(await mountLayout())).toContainEqual(
      expect.objectContaining({ label: 'Demandes de compte', to: '/admin/account-requests' }),
    )
  })

  it('shows the control page entry to an admin, before the account requests', async () => {
    testPage.props.auth.user = { ...testUser, role: 'admin' }

    const items = menuItems(await mountLayout())
    const labels = items.map((item) => item.label)
    expect(items).toContainEqual(
      expect.objectContaining({ label: 'Contrôle', to: '/admin/control' }),
    )
    expect(labels.indexOf('Contrôle')).toBeLessThan(labels.indexOf('Demandes de compte'))
  })

  it('hides the control page entry from a regular user', async () => {
    testPage.props.auth.user = { ...testUser, role: 'user' }

    expect(menuItems(await mountLayout()).map((item) => item.label)).not.toContain('Contrôle')
  })

  it('hides the account requests entry from a regular user', async () => {
    testPage.props.auth.user = { ...testUser, role: 'user' }

    expect(menuItems(await mountLayout()).map((item) => item.label)).not.toContain('Demandes de compte')
  })
})

describe('DashboardLayout flash toasts', () => {
  it('shows a flashed toast message', async () => {
    testPage.props.auth.user = testUser
    await mountLayout()

    document.dispatchEvent(new CustomEvent('inertia:flash', {
      detail: { flash: { toast: { type: 'success', message: 'Le compte de Paul a été créé.' } } },
    }))

    await vi.waitFor(() => expect(document.body.textContent).toContain('Le compte de Paul a été créé.'))
  })
})
