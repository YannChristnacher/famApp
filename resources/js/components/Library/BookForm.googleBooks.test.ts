import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import type { BookDetails, GoogleBooksLookup } from '@/types/library'
import BookForm from './BookForm.vue'

const hobbit: GoogleBooksLookup = {
  volume_id: 'zyTCAlFPjgYC',
  title: 'Le Hobbit',
  author: 'J. R. R. Tolkien',
  cover_url: 'https://books.google.com/books/content?id=zyTCAlFPjgYC&img=1',
}

const book: BookDetails = {
  id: 7,
  title: 'Bilbo',
  author: 'Tolkien',
  isbn: '9782070368228',
  cover_url: '/storage/covers/bilbo.jpg',
  bookshelves: [],
  comment: null,
}

function stubLookup(status: number, body: unknown) {
  const fetchMock = vi.fn(() => Promise.resolve(new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })))
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

async function mountForm(edited: BookDetails | null = null) {
  return mountWithUi(BookForm, { props: { book: edited, bookshelves: [] }, attachTo: document.body })
}

type FormWrapper = Awaited<ReturnType<typeof mountForm>>

function searchButton(wrapper: FormWrapper) {
  return wrapper.find('[data-testid="google-books-search"]')
}

async function search(wrapper: FormWrapper, isbn = '978-2-07-036822-8'): Promise<void> {
  await wrapper.find('input[name="isbn"]').setValue(isbn)
  await searchButton(wrapper).trigger('click')
  await vi.waitFor(() => expect(searchButton(wrapper).attributes('aria-busy')).not.toBe('true'))
  await new Promise((resolve) => setTimeout(resolve))
}

function inputValue(wrapper: FormWrapper, name: string): string {
  return (wrapper.find(`input[name="${name}"]`).element as HTMLInputElement).value
}

function button(wrapper: FormWrapper, label: string) {
  const found = wrapper.findAll('button').find((candidate) => candidate.text().trim() === label)
  expect(found, `button « ${label} »`).toBeDefined()

  return found!
}

function spyOnPost() {
  return vi.spyOn(router, 'post').mockImplementation(() => {})
}

async function submit(wrapper: FormWrapper): Promise<Record<string, unknown>> {
  const post = spyOnPost()
  await wrapper.find('form').trigger('submit')
  await vi.waitFor(() => expect(post).toHaveBeenCalled())

  return post.mock.lastCall![1] as Record<string, unknown>
}

describe('Library/BookForm — Google Books', () => {
  beforeEach(() => {
    URL.createObjectURL = vi.fn(() => 'blob:cover-preview')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    Reflect.deleteProperty(URL, 'createObjectURL')
  })

  it('disables the search while the ISBN is empty', async () => {
    const wrapper = await mountForm()

    expect(searchButton(wrapper).attributes('disabled')).toBeDefined()

    await wrapper.find('input[name="isbn"]').setValue('9782070368228')
    expect(searchButton(wrapper).attributes('disabled')).toBeUndefined()
  })

  it('prefills the empty fields, previews the Google cover and sends the volume', async () => {
    const fetchMock = stubLookup(200, hobbit)
    const wrapper = await mountForm()

    await search(wrapper)

    expect(fetchMock).toHaveBeenCalledWith('/library/google-books?isbn=978-2-07-036822-8', expect.anything())
    expect(inputValue(wrapper, 'title')).toBe('Le Hobbit')
    expect(inputValue(wrapper, 'author')).toBe('J. R. R. Tolkien')
    expect(wrapper.find('[data-testid="google-books-result"]').text()).toContain('Trouvé sur Google Books')
    expect(wrapper.find('[data-testid="google-cover"] img').attributes('src')).toBe(hobbit.cover_url)

    const data = await submit(wrapper)
    expect(data).toMatchObject({
      title: 'Le Hobbit',
      author: 'J. R. R. Tolkien',
      google_volume_id: 'zyTCAlFPjgYC',
      use_google_cover: true,
    })
    expect(data).not.toHaveProperty('google_books_data')
  })

  it('lets the member refuse the Google cover, then take it back', async () => {
    stubLookup(200, hobbit)
    const wrapper = await mountForm()
    await search(wrapper)

    await button(wrapper, 'Ne pas utiliser cette couverture').trigger('click')
    expect(wrapper.find('[data-testid="google-cover"]').exists()).toBe(false)
    expect(await submit(wrapper)).toMatchObject({ google_volume_id: 'zyTCAlFPjgYC', use_google_cover: false })

    await button(wrapper, 'Utiliser la couverture Google').trigger('click')
    expect(wrapper.find('[data-testid="google-cover"]').exists()).toBe(true)
  })

  it('does not offer a cover Google does not have', async () => {
    stubLookup(200, { ...hobbit, cover_url: null })
    const wrapper = await mountForm()
    await search(wrapper)

    expect(wrapper.find('[data-testid="google-cover"]').exists()).toBe(false)
    expect(await submit(wrapper)).toMatchObject({ google_volume_id: 'zyTCAlFPjgYC', use_google_cover: false })
  })

  it('unlinks the volume when the Google Books notice is closed', async () => {
    stubLookup(200, hobbit)
    const wrapper = await mountForm()
    await search(wrapper)

    await wrapper.find('[data-testid="google-books-result"] button[aria-label]').trigger('click')

    expect(wrapper.find('[data-testid="google-books-result"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="google-cover"]').exists()).toBe(false)
    expect(inputValue(wrapper, 'title')).toBe('Le Hobbit')
    expect(await submit(wrapper)).toMatchObject({ google_volume_id: null, use_google_cover: false })
  })

  it('never overwrites a typed title or author without asking', async () => {
    stubLookup(200, hobbit)
    const wrapper = await mountForm()
    await wrapper.find('input[name="title"]').setValue('Bilbo le Hobbit')
    await wrapper.find('input[name="author"]').setValue('')

    await search(wrapper)

    expect(inputValue(wrapper, 'title')).toBe('Bilbo le Hobbit')
    expect(inputValue(wrapper, 'author')).toBe('')
    expect(wrapper.find('[data-testid="google-books-conflict"]').text()).toContain('Le Hobbit')

    await button(wrapper, 'Remplacer').trigger('click')

    expect(inputValue(wrapper, 'title')).toBe('Le Hobbit')
    expect(inputValue(wrapper, 'author')).toBe('J. R. R. Tolkien')
    expect(wrapper.find('[data-testid="google-books-conflict"]').exists()).toBe(false)
  })

  it('keeps the typed fields but still links the volume when asked to', async () => {
    stubLookup(200, hobbit)
    const wrapper = await mountForm()
    await wrapper.find('input[name="title"]').setValue('Bilbo le Hobbit')
    await wrapper.find('input[name="author"]').setValue('Tolkien')

    await search(wrapper)
    await button(wrapper, 'Garder ma saisie').trigger('click')

    expect(wrapper.find('[data-testid="google-books-conflict"]').exists()).toBe(false)
    expect(await submit(wrapper)).toMatchObject({
      title: 'Bilbo le Hobbit',
      author: 'Tolkien',
      google_volume_id: 'zyTCAlFPjgYC',
      use_google_cover: true,
    })
  })

  it.each([
    [404, { message: 'Aucun livre trouvé pour cet ISBN. Vous pouvez le saisir à la main.' }],
    [503, { message: 'Google Books ne répond pas pour le moment. Réessayez plus tard ou saisissez le livre à la main.' }],
    [429, { message: 'Trop de recherches. Veuillez patienter une minute avant de réessayer.' }],
    [422, { message: 'x', errors: { isbn: ['Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'] } }],
  ])('shows the error of a %i answer under the ISBN and changes nothing', async (status, body) => {
    stubLookup(status, body)
    const wrapper = await mountForm()
    await wrapper.find('input[name="title"]').setValue('Ma saisie')

    await search(wrapper)

    const expected = 'errors' in body ? body.errors.isbn[0] : body.message
    expect(wrapper.text()).toContain(expected)
    expect(inputValue(wrapper, 'title')).toBe('Ma saisie')
    expect(wrapper.find('[data-testid="google-books-result"]').exists()).toBe(false)
    expect(await submit(wrapper)).toMatchObject({ google_volume_id: null, use_google_cover: false })
  })

  it('shows a network failure', async () => {
    vi.stubGlobal('fetch', vi.fn(() => Promise.reject(new TypeError('Failed to fetch'))))
    const wrapper = await mountForm()

    await search(wrapper)

    expect(wrapper.text()).toContain('La recherche n’a pas pu aboutir. Vérifiez votre connexion.')
  })

  it('replaces the current cover of an edited book by the Google one', async () => {
    stubLookup(200, hobbit)
    const wrapper = await mountForm(book)

    expect(wrapper.find('[data-testid="current-cover"]').exists()).toBe(true)
    await searchButton(wrapper).trigger('click')
    await vi.waitFor(() => expect(wrapper.find('[data-testid="google-cover"]').exists()).toBe(true))

    expect(wrapper.find('[data-testid="current-cover"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="google-books-conflict"]').exists()).toBe(true)

    const data = await submit(wrapper)
    expect(data).toMatchObject({ _method: 'patch', google_volume_id: 'zyTCAlFPjgYC', use_google_cover: true })
  })
})
