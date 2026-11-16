import { describe, expect, it, vi } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import DashboardLayout from './DashboardLayout.vue'

const props = {
  title: 'Mes étagères',
  breadcrumb: [{ label: 'Bibliothèque', to: '/bibliotheque' }, { label: 'Mes étagères' }],
}

async function mountLayout(options: { attachTo?: HTMLElement } = {}) {
  return mountWithUi(DashboardLayout, {
    props,
    slots: { default: () => 'Contenu de la page' },
    ...options,
  })
}

describe('DashboardLayout', () => {
  it('renders the title given by the page in the navbar', async () => {
    const wrapper = await mountLayout()

    expect(wrapper.findComponent({ name: 'DashboardNavbar' }).text()).toContain('Mes étagères')
  })

  it('renders the breadcrumb given by the page', async () => {
    const wrapper = await mountLayout()
    const breadcrumb = wrapper.findComponent({ name: 'Breadcrumb' })

    expect(breadcrumb.exists()).toBe(true)
    expect(breadcrumb.props('items')).toEqual(props.breadcrumb)
    expect(breadcrumb.text()).toContain('Bibliothèque')
    expect(breadcrumb.text()).toContain('Mes étagères')
  })

  it('renders the page content in the panel body', async () => {
    const wrapper = await mountLayout()

    expect(wrapper.findComponent({ name: 'DashboardPanel' }).text()).toContain('Contenu de la page')
  })

  it('builds a collapsible sidebar from the navigation registry', async () => {
    const wrapper = await mountLayout()

    expect(wrapper.findComponent({ name: 'DashboardGroup' }).exists()).toBe(true)
    const sidebar = wrapper.findComponent({ name: 'DashboardSidebar' })
    expect(sidebar.props('collapsible')).toBe(true)

    const menu = sidebar.findComponent({ name: 'NavigationMenu' })
    expect(menu.props('items')).toEqual([
      { label: 'Accueil', icon: 'i-lucide-house', to: '/', active: true },
    ])
    expect(menu.text()).toContain('Accueil')
  })

  it('opens the search palette with a French empty state', async () => {
    const wrapper = await mountLayout({ attachTo: document.body })
    const button = wrapper.findComponent({ name: 'DashboardSearchButton' })
    expect(button.exists()).toBe(true)

    await button.find('button').trigger('click')

    await vi.waitFor(() => {
      expect(wrapper.findComponent({ name: 'CommandPalette' }).exists()).toBe(true)
      expect(document.body.textContent).toContain('La recherche dans les livres et les outils arrive bientôt.')
    })
    wrapper.unmount()
  })
})
