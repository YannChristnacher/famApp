import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { VisitOptions } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import type { Book, Bookshelf } from '@/types/library'
import BookForm from './BookForm.vue'

const bookshelves: Bookshelf[] = [
  { id: 4, name: 'Fantasy', position: 1 },
  { id: 9, name: 'Polars', position: 2 },
  { id: 2, name: 'Coup de cœur', position: 3 },
]

const book: Book = {
  id: 7,
  title: 'Le Hobbit',
  author: 'J. R. R. Tolkien',
  isbn: '207036822X',
  cover_url: '/storage/covers/hobbit.jpg',
  bookshelves: [{ id: 4, name: 'Fantasy' }],
}

async function mountForm(props: { book: Book | null, bookshelves?: Bookshelf[] }) {
  return mountWithUi(BookForm, { props: { bookshelves, ...props }, attachTo: document.body })
}

type FormWrapper = Awaited<ReturnType<typeof mountForm>>

function bodyOption(text: string): HTMLElement | undefined {
  return Array.from(document.body.querySelectorAll<HTMLElement>('[role="option"]'))
    .find((option) => option.textContent?.trim() === text)
}

async function chooseBookshelves(wrapper: FormWrapper, names: string[]): Promise<void> {
  await wrapper.find('[data-testid="bookshelf-select"]').trigger('click')
  for (const name of names) {
    const option = await vi.waitFor(() => {
      const found = bodyOption(name)
      expect(found).toBeDefined()
      return found!
    })
    option.click()
  }
}

async function chooseCover(wrapper: FormWrapper, file: File): Promise<void> {
  const input = wrapper.find('input[type="file"]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
}

function spyOnPost() {
  return vi.spyOn(router, 'post').mockImplementation(() => {})
}

/** Data of the last visit, after the form's transform. */
function postedData(post: ReturnType<typeof spyOnPost>): Record<string, unknown> {
  return post.mock.lastCall![1] as Record<string, unknown>
}

describe('Library/BookForm', () => {
  // jsdom has no object URLs: UFileUpload previews the chosen image with one.
  beforeEach(() => {
    URL.createObjectURL = vi.fn(() => 'blob:cover-preview')
  })

  afterEach(() => {
    Reflect.deleteProperty(URL, 'createObjectURL')
  })

  it('posts a new book with several bookshelves and a cover, and previews the cover', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ book: null })
    const cover = new File(['cover'], 'couverture.jpg', { type: 'image/jpeg' })

    await wrapper.find('input[name="title"]').setValue('Dune')
    await wrapper.find('input[name="author"]').setValue('Frank Herbert')
    await wrapper.find('input[name="isbn"]').setValue('978-2-07-036822-8')
    await chooseBookshelves(wrapper, ['Polars', 'Coup de cœur'])
    await chooseCover(wrapper, cover)

    await vi.waitFor(() => expect(wrapper.find('img[src="blob:cover-preview"]').exists()).toBe(true))
    expect(wrapper.find('[data-testid="bookshelf-select"]').text()).toContain('Polars')
    expect(wrapper.find('[data-testid="bookshelf-select"]').text()).toContain('Coup de cœur')

    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(
      '/library/books',
      expect.anything(),
      expect.objectContaining({ preserveScroll: true }),
    ))
    expect(postedData(post)).toEqual({
      title: 'Dune',
      author: 'Frank Herbert',
      isbn: '978-2-07-036822-8',
      bookshelf_ids: [9, 2],
      cover,
      remove_cover: false,
    })
  })

  it('posts a book without bookshelf nor cover', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ book: null })

    await wrapper.find('input[name="title"]').setValue('Dune')
    await wrapper.find('input[name="author"]').setValue('Frank Herbert')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalled())
    expect(postedData(post)).toMatchObject({ bookshelf_ids: [], cover: null })
  })

  it('explains that a member without bookshelf keeps the book off the shelves', async () => {
    const wrapper = await mountForm({ book: null, bookshelves: [] })

    expect(wrapper.text()).toContain('Vous n’avez pas encore d’étagère')
  })

  it('shows the server errors under the fields', async () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({
        'title': 'Le champ titre est obligatoire.',
        'isbn': 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.',
        'bookshelf_ids.1': 'Une des étagères choisies n’existe pas.',
        'cover': 'Le champ couverture doit être une image.',
      })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountForm({ book: null })

    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(wrapper.text()).toContain('Le champ titre est obligatoire.'))
    expect(wrapper.text()).toContain('Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.')
    expect(wrapper.text()).toContain('Une des étagères choisies n’existe pas.')
    expect(wrapper.text()).toContain('Le champ couverture doit être une image.')
  })

  it('prefills the edition form and sends it as a PATCH through POST', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ book })

    expect((wrapper.find('input[name="title"]').element as HTMLInputElement).value).toBe('Le Hobbit')
    expect((wrapper.find('input[name="author"]').element as HTMLInputElement).value).toBe('J. R. R. Tolkien')
    expect((wrapper.find('input[name="isbn"]').element as HTMLInputElement).value).toBe('207036822X')
    expect(wrapper.find('[data-testid="bookshelf-select"]').text()).toContain('Fantasy')
    expect(wrapper.find('[data-testid="current-cover"] img').attributes('src')).toBe('/storage/covers/hobbit.jpg')

    await chooseBookshelves(wrapper, ['Polars'])
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith('/library/books/7', expect.anything(), expect.anything()))
    expect(postedData(post)).toEqual({
      _method: 'patch',
      title: 'Le Hobbit',
      author: 'J. R. R. Tolkien',
      isbn: '207036822X',
      bookshelf_ids: [4, 9],
      cover: null,
      remove_cover: false,
    })
  })

  it('removes the current cover, and can cancel the removal', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ book })

    const remove = wrapper.findAll('button').find((button) => button.text() === 'Retirer la couverture')!
    await remove.trigger('click')

    expect(wrapper.find('[data-testid="current-cover"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('La couverture sera retirée.')

    await wrapper.findAll('button').find((button) => button.text() === 'Annuler')!.trigger('click')
    expect(wrapper.find('[data-testid="current-cover"]').exists()).toBe(true)

    await wrapper.findAll('button').find((button) => button.text() === 'Retirer la couverture')!.trigger('click')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalled())
    expect(postedData(post)).toMatchObject({ _method: 'patch', remove_cover: true, cover: null })
  })

  it('replaces the current cover by the chosen file', async () => {
    const wrapper = await mountForm({ book })

    await chooseCover(wrapper, new File(['cover'], 'nouvelle.png', { type: 'image/png' }))

    await vi.waitFor(() => expect(wrapper.find('img[src="blob:cover-preview"]').exists()).toBe(true))
    expect(wrapper.find('[data-testid="current-cover"]').exists()).toBe(false)
  })
})
