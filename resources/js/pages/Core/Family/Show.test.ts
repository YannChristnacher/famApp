import { describe, expect, it, vi } from 'vitest'
import type { VueWrapper } from '@vue/test-utils'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { Book, Bookshelf } from '@/types/library'
import type { Tool, ToolShelf } from '@/types/garage'
import type { MemberFilters, MemberTab } from '@/types/family'
import Show from './Show.vue'

const bookshelves: Bookshelf[] = [
  { id: 4, name: 'Fantasy', position: 1 },
  { id: 6, name: 'Polars', position: 2 },
]
const books: Book[] = [
  { id: 7, title: 'Le Hobbit', author: 'J. R. R. Tolkien', isbn: null, cover_url: null, bookshelves: [{ id: 4, name: 'Fantasy' }] },
  { id: 8, title: 'Dune', author: 'Frank Herbert', isbn: null, cover_url: '/storage/covers/dune.jpg', bookshelves: [] },
]
const toolShelves: ToolShelf[] = [{ id: 2, name: 'Établi', position: 1 }]
const tools: Tool[] = [
  { id: 11, name: 'Perceuse', brand: { id: 1, name: 'Bosch' }, thumbnail_url: null, shelves: [{ id: 2, name: 'Établi' }] },
]

interface PageProps {
  own: boolean
  tab: MemberTab
  books: Book[]
  tools: Tool[]
  filters: MemberFilters
}

const defaults: PageProps = { own: false, tab: 'library', books, tools, filters: { bookshelf: null, shelf: null } }

async function mountPage(overrides: Partial<PageProps> = {}) {
  testPage.props.auth.user = testUser
  testPage.url = '/family/2'

  return mountWithUi(Show, {
    props: { member: { id: 2, name: 'Paul' }, bookshelves, toolShelves, ...defaults, ...overrides },
    attachTo: document.body,
  })
}

function tabButton(wrapper: VueWrapper, label: string) {
  return wrapper.findAll('[role="tab"]').find((tab) => tab.text() === label)!
}

async function openGarage(wrapper: VueWrapper): Promise<void> {
  const garage = tabButton(wrapper, 'Garage')
  await garage.trigger('mousedown', { button: 0 })
  await garage.trigger('focus')
  await vi.waitFor(() => expect(wrapper.find('[data-testid="tool"]').exists()).toBe(true))
}

/** Hrefs of every link and labels of every button: no write action may be among them. */
function actions(wrapper: VueWrapper): string[] {
  return [
    ...wrapper.findAll('a').map((link) => link.attributes('href') ?? ''),
    ...wrapper.findAll('button').map((button) => button.text()),
  ]
}

function expectNoWriteAction(wrapper: VueWrapper): void {
  const all = actions(wrapper)
  expect(all.filter((action) => /\/(create|edit)$|bookshelves|garage\/shelves|^\/library\/books$|^\/garage\/tools$/.test(action))).toEqual([])
  expect(all.filter((action) => /Ajouter|Modifier|Supprimer|Gérer|étagères/.test(action))).toEqual([])
}

describe('Core/Family/Show', () => {
  it('shows the library of the member with the reused book cards and the breadcrumb', async () => {
    const wrapper = await mountPage()

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.props('title')).toBe('Paul')
    expect(layout.props('breadcrumb')).toEqual([{ label: 'La famille', to: '/family' }, { label: 'Paul' }])
    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Bibliothèque', 'Garage'])
    expect(wrapper.findAllComponents({ name: 'BookCard' })).toHaveLength(2)
    expect(wrapper.findAll('[data-testid="book"]').map((card) => card.attributes('href'))).toEqual(['/library/books/7', '/library/books/8'])
    expect(wrapper.find('[data-testid="tool"]').exists()).toBe(false)
  })

  it('filters the books by bookshelf with links to the page of the member', async () => {
    const wrapper = await mountPage({ filters: { bookshelf: 4, shelf: null } })

    const filters = wrapper.findAll('[data-testid="bookshelf-filter"]')
    expect(filters.map((filter) => [filter.text(), filter.attributes('href'), filter.attributes('aria-current')])).toEqual([
      ['Tous les livres', '/family/2', undefined],
      ['Fantasy', '/family/2?bookshelf=4', 'page'],
      ['Polars', '/family/2?bookshelf=6', undefined],
    ])
  })

  it('shows the garage tab with the reused tool cards and shelf filters that keep the tab', async () => {
    const wrapper = await mountPage()

    await openGarage(wrapper)

    expect(wrapper.findAllComponents({ name: 'ToolCard' })).toHaveLength(1)
    expect(wrapper.find('[data-testid="tool"]').attributes('href')).toBe('/garage/tools/11')
    expect(wrapper.find('[data-testid="tool-brand"]').text()).toBe('Bosch')
    expect(wrapper.findAll('[data-testid="shelf-filter"]').map((filter) => [filter.text(), filter.attributes('href')])).toEqual([
      ['Tous les outils', '/family/2?tab=garage'],
      ['Établi', '/family/2?tab=garage&shelf=2'],
    ])
  })

  it('opens on the garage tab when asked', async () => {
    const wrapper = await mountPage({ tab: 'garage', filters: { bookshelf: null, shelf: 2 } })

    expect(wrapper.find('[data-testid="tool"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="book"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="shelf-filter"][aria-current="page"]').text()).toBe('Établi')
  })

  it('offers no write action on the page of another member, in either tab', async () => {
    const wrapper = await mountPage()

    expectNoWriteAction(wrapper)
    await openGarage(wrapper)
    expectNoWriteAction(wrapper)
  })

  it('links their own page to « Mes livres » and « Mes outils »', async () => {
    const wrapper = await mountPage({ own: true })

    expect(wrapper.find('[data-testid="manage-books"]').attributes('href')).toBe('/library/books')
    await openGarage(wrapper)
    expect(wrapper.find('[data-testid="manage-tools"]').attributes('href')).toBe('/garage/tools')
  })

  it('names the member in the empty states', async () => {
    const wrapper = await mountPage({ books: [], tools: [] })

    expect(wrapper.text()).toContain("Paul n'a encore aucun livre.")
    await tabButton(wrapper, 'Garage').trigger('mousedown', { button: 0 })
    await vi.waitFor(() => expect(wrapper.text()).toContain("Paul n'a encore aucun outil."))
  })

  it('names the filtered bookshelf when it is empty', async () => {
    const wrapper = await mountPage({ books: [], filters: { bookshelf: 6, shelf: null } })

    expect(wrapper.text()).toContain("Aucun livre sur l'étagère « Polars ».")
  })

  it('speaks to the member on their own empty page', async () => {
    const wrapper = await mountPage({ own: true, books: [] })

    expect(wrapper.text()).toContain("Vous n'avez encore aucun livre.")
  })
})
