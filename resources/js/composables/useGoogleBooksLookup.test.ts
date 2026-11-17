import { afterEach, describe, expect, it, vi } from 'vitest'
import { useGoogleBooksLookup } from './useGoogleBooksLookup'

function answer(status: number, body: unknown): Response {
  return new Response(body === undefined ? 'not json' : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function stubFetch(response: Response | Error) {
  const fetchMock = vi.fn(() => response instanceof Error ? Promise.reject(response) : Promise.resolve(response))
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

describe('useGoogleBooksLookup', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('asks the server for the ISBN as JSON and returns the prefill', async () => {
    const prefill = { volume_id: 'zyTCAlFPjgYC', title: 'Le Hobbit', author: 'J. R. R. Tolkien', cover_url: null }
    const fetchMock = stubFetch(answer(200, prefill))
    const { lookup, searching, error } = useGoogleBooksLookup()

    const pending = lookup('978-2-07-036822-8')
    expect(searching.value).toBe(true)

    expect(await pending).toEqual(prefill)
    expect(searching.value).toBe(false)
    expect(error.value).toBeNull()
    const [url, init] = fetchMock.mock.lastCall as unknown as [string, RequestInit]
    expect(url).toBe('/library/google-books?isbn=978-2-07-036822-8')
    expect(init.headers).toMatchObject({ Accept: 'application/json' })
    expect(init.credentials).toBe('same-origin')
  })

  it.each([
    [404, { message: 'Aucun livre trouvé pour cet ISBN. Vous pouvez le saisir à la main.' }, 'Aucun livre trouvé pour cet ISBN. Vous pouvez le saisir à la main.'],
    [503, { message: 'Google Books ne répond pas pour le moment.' }, 'Google Books ne répond pas pour le moment.'],
    [429, { message: 'Trop de recherches.' }, 'Trop de recherches.'],
    [422, { message: 'ignored', errors: { isbn: ['Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'] } }, 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'],
    [401, { message: 'Unauthenticated.' }, 'Votre session a expiré. Rechargez la page pour vous reconnecter.'],
    [500, undefined, 'La recherche n’a pas pu aboutir. Réessayez ou saisissez le livre à la main.'],
  ])('shows the French message of a %i answer', async (status, body, message) => {
    stubFetch(answer(status, body))
    const { lookup, error } = useGoogleBooksLookup()

    expect(await lookup('9782070368228')).toBeNull()
    expect(error.value).toBe(message)
  })

  it('reports a network failure', async () => {
    stubFetch(new TypeError('Failed to fetch'))
    const { lookup, error, searching } = useGoogleBooksLookup()

    expect(await lookup('9782070368228')).toBeNull()
    expect(error.value).toBe('La recherche n’a pas pu aboutir. Vérifiez votre connexion.')
    expect(searching.value).toBe(false)
  })

  it('clears the previous error on a new search', async () => {
    stubFetch(answer(404, { message: 'Aucun livre trouvé.' }))
    const { lookup, error } = useGoogleBooksLookup()
    await lookup('9782070368228')

    stubFetch(answer(200, { volume_id: 'a', title: 'T', author: '', cover_url: null }))
    await lookup('9782070368228')

    expect(error.value).toBeNull()
  })
})
