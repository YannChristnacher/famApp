import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { FamilyMember } from '@/types/family'
import Index from './Index.vue'

const members: FamilyMember[] = [
  { id: 3, name: 'Anne', books_count: 0, tools_count: 1, own: false },
  { id: 1, name: 'Marie Dupont', books_count: 1, tools_count: 0, own: true },
  { id: 2, name: 'Paul', books_count: 12, tools_count: 4, own: false },
]

async function mountPage(listed: FamilyMember[] = members) {
  testPage.props.auth.user = testUser

  return mountWithUi(Index, { props: { members: listed }, attachTo: document.body })
}

describe('Core/Family/Index', () => {
  it('lists every member with their numbers of books and tools and a link to their page', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('title')).toBe('La famille')
    const rows = wrapper.findAll('[data-testid="family-member"]')
    expect(rows.map((row) => row.text())).toEqual([
      expect.stringContaining('Anne'),
      expect.stringContaining('Marie Dupont'),
      expect.stringContaining('Paul'),
    ])
    expect(rows[0]!.text()).toContain('0 livre · 1 outil')
    expect(rows[1]!.text()).toContain('1 livre · 0 outil')
    expect(rows[2]!.text()).toContain('12 livres · 4 outils')
    expect(rows.map((row) => row.find('a').attributes('href'))).toEqual(['/family/3', '/family/1', '/family/2'])
  })

  it('marks the own row of the signed-in member', async () => {
    const wrapper = await mountPage()

    const own = wrapper.findAll('[data-testid="family-member"]').filter((row) => row.find('[data-testid="family-member-own"]').exists())
    expect(own).toHaveLength(1)
    expect(own[0]!.text()).toContain('Marie Dupont')
    expect(own[0]!.text()).toContain('Vous')
  })

  it('never shows an email', async () => {
    const wrapper = await mountPage()

    expect(wrapper.text()).not.toContain('@')
  })

  it('shows an empty state without members', async () => {
    const wrapper = await mountPage([])

    expect(wrapper.text()).toContain('Aucun membre pour le moment.')
    expect(wrapper.find('[data-testid="family-member"]').exists()).toBe(false)
  })
})
