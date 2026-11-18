import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountWithUi } from '@/test/mountWithUi'
import { createBookBarcodeDetector } from '@/support/library/barcodeDetector'
import type { BookDetails, GoogleBooksLookup } from '@/types/library'
import BookForm from './BookForm.vue'

vi.mock('@/support/library/barcodeDetector', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/support/library/barcodeDetector')>()),
  createBookBarcodeDetector: vi.fn(),
}))

const SCANNED = '9782070368228'

const hobbit: GoogleBooksLookup = {
  volume_id: 'zyTCAlFPjgYC',
  title: 'Le Hobbit',
  author: 'J. R. R. Tolkien',
  cover_url: null,
}

const book: BookDetails = {
  id: 7,
  title: 'Bilbo',
  author: 'Tolkien',
  isbn: null,
  cover_url: null,
  bookshelves: [],
  comment: null,
}

function stubLookup() {
  const fetchMock = vi.fn((url: string) => {
    void url

    return Promise.resolve(new Response(JSON.stringify(hobbit), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }))
  })
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

function stubScanner(rawValue: string) {
  const tracks = [{ stop: vi.fn() }]
  const stream = Object.assign(new MediaStream(), { getTracks: () => tracks }) as unknown as MediaStream
  Object.defineProperty(navigator, 'mediaDevices', {
    value: { getUserMedia: vi.fn(() => Promise.resolve(stream)) },
    configurable: true,
  })
  vi.mocked(createBookBarcodeDetector).mockResolvedValue({
    detect: vi.fn(() => Promise.resolve([{ rawValue }])),
  })

  return tracks
}

async function mountForm(edited: BookDetails | null = null) {
  return mountWithUi(BookForm, { props: { book: edited, bookshelves: [] }, attachTo: document.body })
}

type FormWrapper = Awaited<ReturnType<typeof mountForm>>

function inputValue(wrapper: FormWrapper, name: string): string {
  return (wrapper.find(`input[name="${name}"]`).element as HTMLInputElement).value
}

async function scan(wrapper: FormWrapper): Promise<void> {
  await wrapper.find('[data-testid="isbn-scan"]').trigger('click')
  await flushPromises()
}

describe('Library/BookForm — barcode scanner', () => {
  beforeEach(() => {
    vi.mocked(createBookBarcodeDetector).mockReset()
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockImplementation(() => Promise.resolve())
  })

  afterEach(() => {
    Reflect.deleteProperty(navigator, 'mediaDevices')
    vi.unstubAllGlobals()
  })

  it('opens the scanner modal from the « Scanner » button', async () => {
    stubScanner('')
    const wrapper = await mountForm()

    expect(wrapper.find('[data-testid="isbn-scan"]').text()).toContain('Scanner')
    await scan(wrapper)

    await vi.waitFor(() => expect(document.body.querySelector('[data-testid="isbn-scanner-video"]')).not.toBeNull())
  })

  it('fills the ISBN and searches Google Books exactly once after a scan', async () => {
    const fetchMock = stubLookup()
    const tracks = stubScanner(SCANNED)
    const wrapper = await mountForm()

    await scan(wrapper)

    await vi.waitFor(() => expect(inputValue(wrapper, 'title')).toBe('Le Hobbit'))
    expect(inputValue(wrapper, 'isbn')).toBe(SCANNED)
    expect(inputValue(wrapper, 'author')).toBe('J. R. R. Tolkien')
    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock.mock.calls[0]?.[0]).toBe(`/library/google-books?isbn=${SCANNED}`)
    expect(tracks[0]?.stop).toHaveBeenCalled()
    expect(document.body.querySelector('[data-testid="isbn-scanner-video"]')).toBeNull()

    await new Promise((resolve) => setTimeout(resolve, 500))
    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('keeps the typed fields of an edited book and proposes to replace them', async () => {
    stubLookup()
    stubScanner(SCANNED)
    const wrapper = await mountForm(book)

    await scan(wrapper)

    await vi.waitFor(() => expect(wrapper.find('[data-testid="google-books-conflict"]').exists()).toBe(true))
    expect(inputValue(wrapper, 'isbn')).toBe(SCANNED)
    expect(inputValue(wrapper, 'title')).toBe('Bilbo')
  })

  it('does not search when the scanned code is not a book', async () => {
    const fetchMock = stubLookup()
    stubScanner('3017620422003')
    const wrapper = await mountForm()

    await scan(wrapper)
    await vi.waitFor(() => expect(document.body.textContent).toContain('n’est pas celui d’un livre'))

    expect(inputValue(wrapper, 'isbn')).toBe('')
    expect(fetchMock).not.toHaveBeenCalled()
  })
})
