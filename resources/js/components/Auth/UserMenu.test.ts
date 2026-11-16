import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import UserMenu from './UserMenu.vue'

describe('UserMenu', () => {
  it('shows the name of the signed-in user', async () => {
    testPage.props.auth.user = testUser
    const wrapper = await mountWithUi(UserMenu)

    expect(wrapper.findComponent({ name: 'DropdownMenu' }).exists()).toBe(true)
    expect(wrapper.text()).toContain('Marie Dupont')
  })

  it('hides the name when the sidebar is collapsed', async () => {
    testPage.props.auth.user = testUser
    const wrapper = await mountWithUi(UserMenu, { props: { collapsed: true } })

    expect(wrapper.text()).not.toContain('Marie Dupont')
  })

  it('renders nothing for guests', async () => {
    const wrapper = await mountWithUi(UserMenu)

    expect(wrapper.findComponent({ name: 'DropdownMenu' }).exists()).toBe(false)
  })

  it('logs the user out with a POST to the logout route', async () => {
    testPage.props.auth.user = testUser
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountWithUi(UserMenu, { attachTo: document.body })

    const trigger = wrapper.find('button')
    await trigger.trigger('pointerdown', { button: 0, ctrlKey: false, pointerType: 'mouse' })
    await trigger.trigger('keydown', { key: 'Enter' })

    const item = await vi.waitFor(() => {
      const found = Array.from(document.body.querySelectorAll('[role="menuitem"]'))
        .find((element) => element.textContent?.includes('Se déconnecter'))
      expect(found).toBeDefined()
      return found as HTMLElement
    })
    expect(document.body.textContent).toContain('marie@example.com')

    item.click()

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith('/logout'))
  })
})
