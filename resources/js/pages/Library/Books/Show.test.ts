import { describe, expect, it, vi } from 'vitest'
import type { VisitOptions } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { Book } from '@/types/library'
import Show from './Show.vue'

const book: Book = {
  id: 7,
  title: 'Le Hobbit',
  author: 'J. R. R. Tolkien',
  isbn: '207036822X',
  cover_url: '/storage/covers/hobbit.jpg',
  bookshelves: [{ id: 4, name: 'Fantasy' }, { id: 2, name: 'Coup de cœur' }],
}

const owner = { id: testUser.id, name: testUser.name }
const paul = { id: 2, name: 'Paul' }

/** The page as seen by the owner, unless `editable` is false (another member). */
async function mountPage(shown: Book = book, editable = true) {
  testPage.props.auth.user = testUser

  return mountWithUi(Show, { props: { book: shown, owner: editable ? owner : paul, editable }, attachTo: document.body })
}

function bodyButton(text: string): HTMLButtonElement | undefined {
  return Array.from(document.body.querySelectorAll('button')).find((button) => button.textContent?.trim() === text)
}

async function openDeleteModal(wrapper: Awaited<ReturnType<typeof mountPage>>): Promise<void> {
  await wrapper.findAll('button').find((button) => button.text() === 'Supprimer')!.trigger('click')
  await vi.waitFor(() => expect(document.body.textContent).toContain('Supprimer le livre'))
}

describe('Library/Books/Show', () => {
  it('shows the book with its cover, ISBN and bookshelves', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('title')).toBe('Le Hobbit')
    expect(wrapper.text()).toContain('J. R. R. Tolkien')
    expect(wrapper.find('[data-testid="isbn"]').text()).toBe('207036822X')
    expect(wrapper.find('img').attributes('src')).toBe('/storage/covers/hobbit.jpg')
    expect(wrapper.findAll('[data-testid="book-bookshelf"]').map((link) => [link.text(), link.attributes('href')])).toEqual([
      ['Fantasy', '/library/books?bookshelf=4'],
      ['Coup de cœur', '/library/books?bookshelf=2'],
    ])
    expect(wrapper.find('a[href="/library/books/7/edit"]').text()).toContain('Modifier')
  })

  it('shows a book without cover, ISBN nor bookshelf', async () => {
    const wrapper = await mountPage({ ...book, isbn: null, cover_url: null, bookshelves: [] })

    expect(wrapper.find('[data-testid="cover-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="isbn"]').text()).toBe('Non renseigné')
    expect(wrapper.text()).toContain('Rangé sur aucune étagère')
  })

  it('asks for a confirmation naming the book before deleting it', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {})
    const wrapper = await mountPage()

    await openDeleteModal(wrapper)

    expect(document.body.textContent).toContain('Le livre « Le Hobbit » et sa couverture seront supprimés définitivement.')
    expect(destroy).not.toHaveBeenCalled()
  })

  it('does nothing when the deletion is cancelled', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {})
    const wrapper = await mountPage()

    await openDeleteModal(wrapper)
    bodyButton('Annuler')!.click()

    await vi.waitFor(() => expect(document.body.textContent).not.toContain('Supprimer le livre'))
    expect(destroy).not.toHaveBeenCalled()
  })

  it('deletes the book once confirmed', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation((_url, options?: VisitOptions) => {
      options?.onSuccess?.({} as never)
    })
    const wrapper = await mountPage()

    await openDeleteModal(wrapper)
    const buttons = Array.from(document.body.querySelectorAll('[role="dialog"] button'))
      .filter((button) => button.textContent?.trim() === 'Supprimer') as HTMLButtonElement[]
    buttons[0]!.click()

    await vi.waitFor(() => expect(destroy).toHaveBeenCalledWith('/library/books/7', expect.any(Object)))
  })

  it('is read-only for another member: owner named, no action, bookshelves on the page of the member', async () => {
    const wrapper = await mountPage(book, false)

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('breadcrumb')).toEqual([
      { label: 'La famille', to: '/family' },
      { label: 'Paul', to: '/family/2' },
      { label: 'Le Hobbit' },
    ])
    expect(wrapper.find('[data-testid="owner"]').text()).toBe('Dans la bibliothèque de Paul')
    expect(wrapper.findAll('[data-testid="book-bookshelf"]').map((link) => link.attributes('href'))).toEqual([
      '/family/2?bookshelf=4',
      '/family/2?bookshelf=2',
    ])
    expect(wrapper.find('a[href="/library/books/7/edit"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Modifier')
    expect(wrapper.text()).not.toContain('Supprimer')
    expect(document.body.textContent).not.toContain('Supprimer le livre')
  })

  it('shows the actions and the « Mes livres » breadcrumb to the owner', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('breadcrumb')).toEqual([
      { label: 'Bibliothèque' },
      { label: 'Mes livres', to: '/library/books' },
      { label: 'Le Hobbit' },
    ])
    expect(wrapper.find('[data-testid="owner"]').exists()).toBe(false)
    expect(wrapper.findAll('button').map((button) => button.text())).toContain('Supprimer')
  })
})
