import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { Book, Bookshelf } from '@/types/library'
import Index from './Index.vue'

const bookshelves: Bookshelf[] = [
  { id: 4, name: 'Fantasy', position: 1 },
  { id: 9, name: 'Polars', position: 2 },
]

const books: Book[] = [
  {
    id: 12,
    title: 'Dune',
    author: 'Frank Herbert',
    isbn: null,
    cover_url: null,
    bookshelves: [],
  },
  {
    id: 3,
    title: 'Le Hobbit',
    author: 'J. R. R. Tolkien',
    isbn: '207036822X',
    cover_url: '/storage/covers/hobbit.jpg',
    bookshelves: [{ id: 4, name: 'Fantasy' }],
  },
]

async function mountPage(props: { books?: Book[], bookshelves?: Bookshelf[], filter?: number | null } = {}) {
  testPage.props.auth.user = testUser
  testPage.url = '/library/books'

  return mountWithUi(Index, {
    props: { books, bookshelves, filter: null, ...props },
    attachTo: document.body,
  })
}

type PageWrapper = Awaited<ReturnType<typeof mountPage>>

function cards(wrapper: PageWrapper) {
  return wrapper.findAll('[data-testid="book"]')
}

function filters(wrapper: PageWrapper) {
  return wrapper.findAll('[data-testid="bookshelf-filter"]')
}

describe('Library/Books/Index', () => {
  it('renders the books as cards with their cover or a neutral placeholder', async () => {
    const wrapper = await mountPage()

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.props('title')).toBe('Mes livres')

    const [dune, hobbit] = cards(wrapper)
    expect(cards(wrapper)).toHaveLength(2)
    expect(dune!.attributes('href')).toBe('/library/books/12')
    expect(dune!.text()).toContain('Dune')
    expect(dune!.text()).toContain('Frank Herbert')
    expect(dune!.find('img').exists()).toBe(false)
    expect(dune!.find('[data-testid="cover-placeholder"]').attributes('aria-label')).toBe('Pas de couverture pour Dune')

    expect(hobbit!.attributes('href')).toBe('/library/books/3')
    expect(hobbit!.find('img').attributes('src')).toBe('/storage/covers/hobbit.jpg')
    expect(hobbit!.find('img').attributes('alt')).toBe('Couverture de Le Hobbit')
  })

  it('links to the creation form and to the bookshelves', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('a[href="/library/books/create"]').text()).toContain('Ajouter un livre')
    expect(wrapper.find('a[href="/library/bookshelves"]').text()).toContain('Mes étagères')
  })

  it('offers every bookshelf as a filter and highlights the current one', async () => {
    const all = await mountPage()

    expect(filters(all).map((filter) => [filter.text(), filter.attributes('href')])).toEqual([
      ['Tous mes livres', '/library/books'],
      ['Fantasy', '/library/books?bookshelf=4'],
      ['Polars', '/library/books?bookshelf=9'],
    ])
    expect(filters(all).map((filter) => filter.attributes('aria-current'))).toEqual(['page', undefined, undefined])
  })

  it('highlights the bookshelf the list is filtered on', async () => {
    const filtered = await mountPage({ books: [books[1]!], filter: 4 })
    expect(filters(filtered).map((filter) => filter.attributes('aria-current'))).toEqual([undefined, 'page', undefined])
    expect(cards(filtered)).toHaveLength(1)
  })

  it('hides the filter when the member has no bookshelf', async () => {
    const wrapper = await mountPage({ bookshelves: [] })

    expect(filters(wrapper)).toHaveLength(0)
  })

  it('invites to add the first book when the library has none', async () => {
    const wrapper = await mountPage({ books: [] })

    expect(cards(wrapper)).toHaveLength(0)
    expect(wrapper.text()).toContain("Vous n'avez encore aucun livre.")
    expect(wrapper.findAll('a[href="/library/books/create"]').length).toBe(2)
  })

  it('says when the filtered bookshelf holds no book', async () => {
    const wrapper = await mountPage({ books: [], filter: 9 })

    expect(wrapper.text()).toContain('Aucun livre sur l\'étagère « Polars ».')
    expect(wrapper.text()).not.toContain("Vous n'avez encore aucun livre.")
  })
})
