import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import DashboardLayout from './DashboardLayout.vue'

async function libraryEntry() {
  const wrapper = await mountWithUi(DashboardLayout, { props: { title: 'Ma bibliothèque', breadcrumb: [] } })
  const items = wrapper.findComponent({ name: 'NavigationMenu' }).props('items') as { label: string, to: string, active: boolean }[]

  return items.find((item) => item.label === 'Bibliothèque')
}

describe('DashboardLayout library entry from the real registry', () => {
  it.each(['user', 'admin'] as const)('shows the library entry to a signed-in %s', async (role) => {
    testPage.props.auth.user = { ...testUser, role }

    expect(await libraryEntry()).toEqual(expect.objectContaining({ to: '/library', icon: 'i-lucide-library-big' }))
  })

  it('marks the library entry active on the pages of the module', async () => {
    testPage.props.auth.user = testUser
    testPage.url = '/library/bookshelves'

    expect((await libraryEntry())?.active).toBe(true)
  })

  it('never shows the library entry to a guest', async () => {
    expect(await libraryEntry()).toBeUndefined()
  })
})
