import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { Brand, Tool, ToolFilters, ToolShelf } from '@/types/garage'
import Index from './Index.vue'

const shelves: ToolShelf[] = [
  { id: 4, name: 'Électroportatif', position: 1 },
  { id: 9, name: 'Établi', position: 2 },
]

const brands: Brand[] = [{ id: 3, name: 'Bosch' }, { id: 5, name: 'Makita' }]

const tools: Tool[] = [
  { id: 2, name: 'Marteau', brand: null, thumbnail_url: null, shelves: [] },
  { id: 7, name: 'Perceuse', brand: { id: 3, name: 'Bosch' }, thumbnail_url: '/storage/tools/front.jpg', shelves: [{ id: 4, name: 'Électroportatif' }] },
]

async function mountPage(props: { tools?: Tool[], shelves?: ToolShelf[], brands?: Brand[], filters?: ToolFilters } = {}) {
  testPage.props.auth.user = testUser

  return mountWithUi(Index, {
    props: { tools, shelves, brands, filters: { shelf: null, brand: null }, ...props },
    attachTo: document.body,
  })
}

describe('Garage/Tools/Index', () => {
  it('shows the tools as cards with their thumbnail or a neutral image, name and brand', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('title')).toBe('Mes outils')
    const cards = wrapper.findAll('[data-testid="tool"]')
    expect(cards.map((card) => card.attributes('href'))).toEqual(['/garage/tools/2', '/garage/tools/7'])
    expect(cards[0]!.find('[data-testid="thumbnail-placeholder"]').attributes('aria-label')).toBe('Pas de photo pour Marteau')
    expect(cards[0]!.find('[data-testid="tool-brand"]').exists()).toBe(false)
    expect(cards[1]!.find('img').attributes('src')).toBe('/storage/tools/front.jpg')
    expect(cards[1]!.find('[data-testid="tool-brand"]').text()).toBe('Bosch')
    expect(wrapper.find('a[href="/garage/tools/create"]').text()).toContain('Ajouter un outil')
    expect(wrapper.find('a[href="/garage/shelves"]').text()).toContain('Mes étagères')
  })

  it('links the shelf filters, keeping the brand filter and marking the active one', async () => {
    const wrapper = await mountPage({ filters: { shelf: 9, brand: 3 } })

    const filters = wrapper.findAll('[data-testid="shelf-filter"]')
    expect(filters.map((filter) => [filter.text(), filter.attributes('href')])).toEqual([
      ['Toutes les étagères', '/garage/tools?brand=3'],
      ['Électroportatif', '/garage/tools?shelf=4&brand=3'],
      ['Établi', '/garage/tools?shelf=9&brand=3'],
    ])
    expect(filters.map((filter) => filter.attributes('aria-current'))).toEqual([undefined, undefined, 'page'])
  })

  it('filters by brand, keeping the shelf filter', async () => {
    const get = vi.spyOn(router, 'get').mockImplementation(() => {})
    const wrapper = await mountPage({ filters: { shelf: 4, brand: null } })

    const select = wrapper.findComponent({ name: 'Select' })
    expect(select.props('modelValue')).toBe('all')
    expect(select.props('items')).toEqual([
      { label: 'Toutes les marques', value: 'all' },
      { label: 'Bosch', value: '3' },
      { label: 'Makita', value: '5' },
    ])

    select.vm.$emit('update:modelValue', '5')
    expect(get).toHaveBeenLastCalledWith('/garage/tools?shelf=4&brand=5', {}, expect.objectContaining({ preserveScroll: true }))

    select.vm.$emit('update:modelValue', 'all')
    expect(get).toHaveBeenLastCalledWith('/garage/tools?shelf=4', {}, expect.anything())
  })

  it('hides the filters the member cannot use', async () => {
    const wrapper = await mountPage({ shelves: [], brands: [] })

    expect(wrapper.find('[data-testid="shelf-filter"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="brand-filter"]').exists()).toBe(false)
  })

  it('invites to add a first tool when the garage has none', async () => {
    const wrapper = await mountPage({ tools: [], shelves: [], brands: [] })

    expect(wrapper.text()).toContain('Vous n\'avez encore aucun outil.')
    expect(wrapper.findAll('a[href="/garage/tools/create"]').length).toBeGreaterThan(1)
  })

  it('tells when no tool matches the filters', async () => {
    const wrapper = await mountPage({ tools: [], filters: { shelf: 4, brand: null } })

    expect(wrapper.text()).toContain('Aucun outil ne correspond à ces filtres.')
    expect(wrapper.text()).not.toContain('Vous n\'avez encore aucun outil.')
    expect(wrapper.text()).toContain('Voir tous mes outils')
  })
})
