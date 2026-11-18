import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import type { SearchGroup } from '@/types/search'
import { SEARCH_DEBOUNCE_MS, useGlobalSearch } from './useGlobalSearch'

const books: SearchGroup = {
  key: 'books',
  label: 'Livres',
  icon: 'i-lucide-book',
  results: [{ type: 'book', id: 3, label: 'Le Hobbit', description: 'Tolkien · chez Paul', url: '/library/books/3', image: null }],
}

function answer(status: number, body: unknown): Response {
  return new Response(body === undefined ? 'not json' : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

interface PendingRequest {
  url: string
  init: RequestInit
  resolve: (response: Response) => void
  reject: (error: unknown) => void
}

/**
 * fetch() whose answers are given by the test, in any order; an aborted
 * request rejects like the browser does.
 */
function stubFetch() {
  const requests: PendingRequest[] = []
  const fetchMock = vi.fn((url: string, init: RequestInit) => new Promise<Response>((resolve, reject) => {
    requests.push({ url, init, resolve, reject })
    init.signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')))
  }))
  vi.stubGlobal('fetch', fetchMock)

  return { fetchMock, requests }
}

function setUp() {
  const term = ref('')
  const scope = effectScope()
  const search = scope.run(() => useGlobalSearch(term))!

  return { term, scope, ...search }
}

async function type(term: { value: string }, value: string) {
  term.value = value
  await nextTick()
}

describe('useGlobalSearch', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('sends one request once the typing pauses', async () => {
    const { fetchMock, requests } = stubFetch()
    const { term, groups, loading, error } = setUp()

    await type(term, 'ho')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS - 50)
    await type(term, 'hob')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS - 50)
    await type(term, ' hobbit ')
    expect(fetchMock).not.toHaveBeenCalled()
    expect(loading.value).toBe(true)

    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(requests[0]!.url).toBe('/search?q=hobbit')
    expect(requests[0]!.init.headers).toMatchObject({ Accept: 'application/json' })
    expect(requests[0]!.init.credentials).toBe('same-origin')
    expect(loading.value).toBe(true)

    requests[0]!.resolve(answer(200, { groups: [books] }))
    await vi.waitFor(() => expect(loading.value).toBe(false))
    expect(groups.value).toEqual([books])
    expect(error.value).toBeNull()
  })

  it('sends nothing for less than 2 characters and clears the results', async () => {
    const { fetchMock, requests } = stubFetch()
    const { term, groups, loading, tooShort } = setUp()
    expect(tooShort.value).toBe(true)

    await type(term, 'hobbit')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)
    requests[0]!.resolve(answer(200, { groups: [books] }))
    await vi.waitFor(() => expect(groups.value).toHaveLength(1))

    await type(term, ' h ')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS * 2)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(groups.value).toEqual([])
    expect(loading.value).toBe(false)
    expect(tooShort.value).toBe(true)
  })

  it('aborts the pending request and ignores its late answer', async () => {
    const { requests } = stubFetch()
    const { term, groups, loading } = setUp()

    await type(term, 'hob')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)
    await type(term, 'dune')
    expect(requests[0]!.init.signal?.aborted).toBe(true)

    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)
    expect(requests[1]!.url).toBe('/search?q=dune')
    requests[0]!.resolve(answer(200, { groups: [books] }))
    await vi.advanceTimersByTimeAsync(0)
    expect(groups.value).toEqual([])
    expect(loading.value).toBe(true)

    requests[1]!.resolve(answer(200, { groups: [] }))
    await vi.waitFor(() => expect(loading.value).toBe(false))
    expect(groups.value).toEqual([])
  })

  it('refuses a text longer than 100 characters without asking the server', async () => {
    const { fetchMock } = stubFetch()
    const { term, error, groups } = setUp()

    await type(term, 'a'.repeat(101))
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)

    expect(fetchMock).not.toHaveBeenCalled()
    expect(groups.value).toEqual([])
    expect(error.value).toBe('La recherche est limitée à 100 caractères.')
  })

  it.each([
    [429, { message: 'Trop de recherches. Veuillez patienter une minute avant de réessayer.' }, 'Trop de recherches. Veuillez patienter une minute avant de réessayer.'],
    [401, { message: 'Unauthenticated.' }, 'Votre session a expiré. Rechargez la page pour vous reconnecter.'],
    [419, { message: 'CSRF token mismatch.' }, 'Votre session a expiré. Rechargez la page pour vous reconnecter.'],
    [500, undefined, 'La recherche n’a pas pu aboutir. Réessayez dans un instant.'],
    [200, { unexpected: true }, 'La recherche n’a pas pu aboutir. Réessayez dans un instant.'],
  ])('shows the French message of a %i answer', async (status, body, message) => {
    const { requests } = stubFetch()
    const { term, error, groups, loading } = setUp()

    await type(term, 'hobbit')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)
    requests[0]!.resolve(answer(status, body))

    await vi.waitFor(() => expect(loading.value).toBe(false))
    expect(error.value).toBe(message)
    expect(groups.value).toEqual([])
  })

  it('reports a network failure and clears it on the next search', async () => {
    const { requests } = stubFetch()
    const { term, error, loading } = setUp()

    await type(term, 'hobbit')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)
    requests[0]!.reject(new TypeError('Failed to fetch'))
    await vi.waitFor(() => expect(loading.value).toBe(false))
    expect(error.value).toBe('La recherche n’a pas pu aboutir. Vérifiez votre connexion.')

    await type(term, 'dune')
    expect(error.value).toBeNull()
  })

  it('aborts the pending request when its scope is disposed', async () => {
    const { requests } = stubFetch()
    const { term, scope } = setUp()

    await type(term, 'hobbit')
    await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE_MS)
    scope.stop()

    expect(requests[0]!.init.signal?.aborted).toBe(true)
  })
})
