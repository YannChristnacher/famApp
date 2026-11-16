import { describe, expect, it, vi } from 'vitest'
import type { VisitOptions } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { AccountRequestRow } from '@/types/admin'
import Index from './Index.vue'

const pending: AccountRequestRow = {
  id: 7,
  name: 'Paul Martin',
  email: 'paul@example.com',
  message: null,
  status: 'pending',
  createdAt: '2026-11-15T12:30:00+00:00',
}

describe('Admin/AccountRequests/Index', () => {
  it('renders the requests table in the dashboard layout', async () => {
    testPage.props.auth.user = { ...testUser, role: 'admin' }
    const wrapper = await mountWithUi(Index, { props: { accountRequests: [pending] } })

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.props('title')).toBe('Demandes de compte')
    expect(layout.props('breadcrumb')).toEqual([{ label: 'Administration' }, { label: 'Demandes de compte' }])
    expect(wrapper.findComponent({ name: 'AccountRequestsTable' }).props('accountRequests')).toEqual([pending])
  })

  it('posts the rejection of a request', async () => {
    testPage.props.auth.user = { ...testUser, role: 'admin' }
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountWithUi(Index, { props: { accountRequests: [pending] } })

    await wrapper.findAll('button').find((button) => button.text() === 'Refuser')?.trigger('click')

    expect(post).toHaveBeenCalledWith('/admin/account-requests/7/reject', {}, expect.objectContaining({ preserveScroll: true }))
  })

  it('marks the request as being rejected until the request finishes', async () => {
    testPage.props.auth.user = { ...testUser, role: 'admin' }
    let options: VisitOptions | undefined
    const post = vi.spyOn(router, 'post').mockImplementation((_url, _data, visitOptions?: VisitOptions) => {
      options = visitOptions
      options?.onStart?.({} as never)
    })
    const wrapper = await mountWithUi(Index, { props: { accountRequests: [pending] } })
    const table = () => wrapper.findComponent({ name: 'AccountRequestsTable' })

    await wrapper.findAll('button').find((button) => button.text() === 'Refuser')?.trigger('click')
    expect(table().props('rejectingId')).toBe(7)

    table().vm.$emit('reject', pending)
    expect(post).toHaveBeenCalledOnce()

    options?.onFinish?.({} as never)
    await wrapper.vm.$nextTick()
    expect(table().props('rejectingId')).toBeNull()
  })
})
