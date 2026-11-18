import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import DashboardLayout from './DashboardLayout.vue'

async function menuItems() {
  const wrapper = await mountWithUi(DashboardLayout, { props: { title: 'La famille', breadcrumb: [] } })

  return wrapper.findComponent({ name: 'NavigationMenu' }).props('items') as { label: string, to: string, active: boolean }[]
}

async function familyEntry() {
  return (await menuItems()).find((item) => item.label === 'La famille')
}

describe('DashboardLayout family entry from the real registry', () => {
  it.each(['user', 'admin'] as const)('shows the family entry to a signed-in %s', async (role) => {
    testPage.props.auth.user = { ...testUser, role }

    expect(await familyEntry()).toEqual(expect.objectContaining({ to: '/family', icon: 'i-lucide-users' }))
  })

  it.each(['/family', '/family/4', '/family/4?tab=garage&shelf=2'])('marks only the family entry active on %s', async (url) => {
    testPage.props.auth.user = testUser
    testPage.url = url

    const items = await menuItems()
    expect(items.filter((item) => item.active).map((item) => item.label)).toEqual(['La famille'])
  })

  it('never shows the family entry to a guest', async () => {
    expect(await familyEntry()).toBeUndefined()
  })
})
