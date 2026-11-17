import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import DashboardLayout from './DashboardLayout.vue'

async function menuItems() {
  const wrapper = await mountWithUi(DashboardLayout, { props: { title: 'Mon garage', breadcrumb: [] } })

  return wrapper.findComponent({ name: 'NavigationMenu' }).props('items') as { label: string, to: string, active: boolean }[]
}

async function garageEntry() {
  return (await menuItems()).find((item) => item.label === 'Garage')
}

describe('DashboardLayout garage entry from the real registry', () => {
  it.each(['user', 'admin'] as const)('shows the garage entry to a signed-in %s', async (role) => {
    testPage.props.auth.user = { ...testUser, role }

    expect(await garageEntry()).toEqual(expect.objectContaining({ to: '/garage', icon: 'i-lucide-warehouse' }))
  })

  it('marks only the garage entry active on the pages of the module', async () => {
    testPage.props.auth.user = testUser
    testPage.url = '/garage/shelves'

    const items = await menuItems()
    expect(items.find((item) => item.label === 'Garage')?.active).toBe(true)
    expect(items.filter((item) => item.active).map((item) => item.label)).toEqual(['Garage'])
  })

  it('never shows the garage entry to a guest', async () => {
    expect(await garageEntry()).toBeUndefined()
  })
})
