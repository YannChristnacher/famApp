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

async function mountPage(shown: Book = book) {
  testPage.props.auth.user = testUser

  return mountWithUi(Show, { props: { book: shown }, attachTo: document.body })
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
})
