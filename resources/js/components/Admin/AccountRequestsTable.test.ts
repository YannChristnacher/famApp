import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import type { AccountRequestRow } from '@/types/admin'
import AccountRequestsTable from './AccountRequestsTable.vue'

const requests: AccountRequestRow[] = [
  {
    id: 3,
    name: 'Paul Martin',
    email: 'paul@example.com',
    message: 'Je suis le cousin de Marie.',
    status: 'pending',
    createdAt: '2026-11-15T12:30:00+00:00',
  },
  { id: 2, name: 'Julie Durand', email: 'julie@example.com', message: null, status: 'approved', createdAt: '2026-11-10T12:00:00+00:00' },
  { id: 1, name: 'Inconnu', email: 'spam@example.com', message: 'Promo', status: 'rejected', createdAt: '2026-11-01T12:00:00+00:00' },
]

async function mountTable(rows = requests, rejectingId: number | null = null) {
  return mountWithUi(AccountRequestsTable, { props: { accountRequests: rows, rejectingId } })
}

describe('Admin/AccountRequestsTable', () => {
  it('renders the requests in a Nuxt UI table with French headers', async () => {
    const wrapper = await mountTable()

    expect(wrapper.findComponent({ name: 'Table' }).exists()).toBe(true)
    const headers = wrapper.findAll('th').map((th) => th.text())
    expect(headers).toEqual(['Nom', 'Adresse email', 'Message', 'Date', 'Statut', 'Actions'])
    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(3)
    expect(rows[0]?.text()).toContain('Paul Martin')
    expect(rows[0]?.text()).toContain('paul@example.com')
    expect(rows[0]?.text()).toContain('Je suis le cousin de Marie.')
    expect(rows[0]?.text()).toContain('15/11/2026')
  })

  it('shows the status in French with a colored badge', async () => {
    const wrapper = await mountTable()

    const badges = wrapper.findAllComponents({ name: 'Badge' })
    expect(badges.map((badge) => [badge.text(), badge.props('color')])).toEqual([
      ['En attente', 'warning'],
      ['Acceptée', 'success'],
      ['Refusée', 'neutral'],
    ])
  })

  it('shows a dash when there is no message', async () => {
    const wrapper = await mountTable()

    expect(wrapper.findAll('tbody tr')[1]?.findAll('td')[2]?.text()).toBe('—')
  })

  it('offers the actions on pending requests only', async () => {
    const wrapper = await mountTable()
    const rows = wrapper.findAll('tbody tr')

    const createLink = rows[0]?.find('a[href="/admin/account-requests/3/account"]')
    expect(createLink?.text()).toBe('Créer le compte')
    expect(rows[0]?.text()).toContain('Refuser')
    expect(rows[1]?.text()).not.toContain('Créer le compte')
    expect(rows[1]?.text()).not.toContain('Refuser')
    expect(rows[2]?.text()).not.toContain('Refuser')
  })

  it('emits reject with the request when « Refuser » is clicked', async () => {
    const wrapper = await mountTable()

    const rejectButton = wrapper.findAll('button').find((button) => button.text() === 'Refuser')
    await rejectButton?.trigger('click')

    expect(wrapper.emitted('reject')).toEqual([[requests[0]]])
  })

  it('shows the rejection in progress and blocks a second click', async () => {
    const second: AccountRequestRow = { ...requests[0]!, id: 4, name: 'Léa Petit', email: 'lea@example.com' }
    const wrapper = await mountTable([requests[0]!, second], 3)

    const rejectButtons = wrapper.findAllComponents({ name: 'Button' }).filter((button) => button.text() === 'Refuser')
    expect(rejectButtons.map((button) => [button.props('loading'), button.props('disabled')])).toEqual([
      [true, true],
      [false, true],
    ])
    await rejectButtons[0]?.trigger('click')
    expect(wrapper.emitted('reject')).toBeUndefined()
  })

  it('says when there is no request', async () => {
    const wrapper = await mountTable([])

    expect(wrapper.text()).toContain('Aucune demande de compte.')
  })
})
