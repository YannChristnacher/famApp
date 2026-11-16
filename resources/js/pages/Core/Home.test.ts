import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ui from '@nuxt/ui/vue-plugin'
import { mountWithUi } from '@/test/mountWithUi'
import Home from './Home.vue'

describe('Core/Home', () => {
  it('renders inside the dashboard layout with its title and breadcrumb', async () => {
    const wrapper = await mountWithUi(Home, { props: { appName: 'famApp' } })

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.exists()).toBe(true)
    expect(layout.props('title')).toBe('Accueil')
    expect(layout.props('breadcrumb')).toEqual([{ label: 'Accueil' }])
  })

  it('displays a welcome message with the application name', async () => {
    const wrapper = await mountWithUi(Home, { props: { appName: 'famApp' } })

    const card = wrapper.findComponent({ name: 'Card' })
    expect(card.exists()).toBe(true)
    expect(card.text()).toContain('Bienvenue sur famApp')
  })

  it('does not wrap itself in its own UApp anymore', async () => {
    const wrapper = mount(Home, { props: { appName: 'famApp' }, global: { plugins: [ui] } })
    await flushPromises()

    expect(wrapper.findComponent({ name: 'App' }).exists()).toBe(false)
  })
})
