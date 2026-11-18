import { afterEach, describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import type { SearchGroup } from '@/types/search'
import GlobalSearch from './GlobalSearch.vue'

const groups: SearchGroup[] = [
  {
    key: 'books',
    label: 'Livres',
    icon: 'i-lucide-book',
    results: [
      { type: 'book', id: 3, label: 'Le Hobbit', description: 'Tolkien · chez Paul', url: '/library/books/3', image: '/storage/covers/hobbit.jpg' },
    ],
  },
  {
    key: 'tools',
    label: 'Outils',
    icon: 'i-lucide-wrench',
    results: [{ type: 'tool', id: 8, label: 'Hache', description: 'chez vous', url: '/garage/tools/8', image: null }],
  },
  {
    key: 'members',
    label: 'Membres',
    icon: 'i-lucide-user',
    results: [{ type: 'member', id: 2, label: 'Paul Hobbes', description: '1 livre · 0 outil', url: '/family/2', image: null }],
  },
]

function answer(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

function stubFetch(response: () => Promise<Response>) {
  const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>(() => response())
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

async function mountOpenSearch() {
  const wrapper = await mountWithUi(GlobalSearch, { props: { open: true }, attachTo: document.body })
  await vi.waitFor(() => expect(document.body.querySelector('input')).not.toBeNull())

  return wrapper
}

async function typeInPalette(text: string) {
  const input = document.body.querySelector('input') as HTMLInputElement
  input.value = text
  input.dispatchEvent(new Event('input'))
}

function bodyText(): string {
  return document.body.textContent ?? ''
}

describe('GlobalSearch', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('keeps the default shortcut of Nuxt UI and no theme group', async () => {
    stubFetch(() => Promise.resolve(answer(200, { groups: [] })))
    const wrapper = await mountOpenSearch()
    const search = wrapper.findComponent({ name: 'DashboardSearch' })

    expect(search.props('shortcut')).toBe('meta_k')
    expect(search.props('colorMode')).toBe(false)
    expect(bodyText()).not.toContain('Thème')
  })

  it('invites to type at least 2 characters', async () => {
    const fetchMock = stubFetch(() => Promise.resolve(answer(200, { groups: [] })))
    await mountOpenSearch()

    expect(bodyText()).toContain('Tapez au moins 2 caractères pour chercher un livre, un outil ou un membre.')

    await typeInPalette('h')
    await new Promise((resolve) => setTimeout(resolve, 400))
    expect(fetchMock).not.toHaveBeenCalled()
    expect(bodyText()).toContain('Tapez au moins 2 caractères')
  })

  it('shows one group per module with the French titles, without local filtering', async () => {
    const fetchMock = stubFetch(() => Promise.resolve(answer(200, { groups })))
    const wrapper = await mountOpenSearch()

    await typeInPalette('hob')

    await vi.waitFor(() => expect(bodyText()).toContain('Le Hobbit'))
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.lastCall?.[0]).toBe('/search?q=hob')
    const paletteGroups = wrapper.findComponent({ name: 'CommandPalette' }).props('groups') as Array<{ id: string, label: string, ignoreFilter: boolean, items: Array<Record<string, unknown>> }>
    expect(paletteGroups.map((group) => [group.id, group.label, group.ignoreFilter])).toEqual([
      ['books', 'Livres', true],
      ['tools', 'Outils', true],
      ['members', 'Membres', true],
    ])
    expect(paletteGroups[0]!.items[0]).toMatchObject({ label: 'Le Hobbit', description: 'Tolkien · chez Paul', avatar: { src: '/storage/covers/hobbit.jpg' } })
    expect(paletteGroups[1]!.items[0]).toMatchObject({ label: 'Hache', icon: 'i-lucide-wrench' })
    for (const text of ['Livres', 'Outils', 'Membres', 'Hache', 'Paul Hobbes', 'Tolkien · chez Paul']) {
      expect(bodyText()).toContain(text)
    }
  })

  it('shows the loading state while the server answers', async () => {
    let finish: (response: Response) => void = () => {}
    const fetchMock = stubFetch(() => new Promise<Response>((resolve) => { finish = resolve }))
    const wrapper = await mountOpenSearch()

    await typeInPalette('hobbit')

    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled())
    expect(wrapper.findComponent({ name: 'DashboardSearch' }).props('loading')).toBe(true)
    expect(bodyText()).toContain('Recherche en cours…')

    finish(answer(200, { groups: [] }))
    await vi.waitFor(() => expect(wrapper.findComponent({ name: 'DashboardSearch' }).props('loading')).toBe(false))
  })

  it('says when nothing matches', async () => {
    stubFetch(() => Promise.resolve(answer(200, { groups: [] })))
    await mountOpenSearch()

    await typeInPalette('zola')

    await vi.waitFor(() => expect(bodyText()).toContain('Aucun résultat pour « zola ».'))
  })

  it('shows the error message of the server', async () => {
    stubFetch(() => Promise.resolve(answer(429, { message: 'Trop de recherches. Veuillez patienter une minute avant de réessayer.' })))
    await mountOpenSearch()

    await typeInPalette('hobbit')

    await vi.waitFor(() => expect(bodyText()).toContain('Trop de recherches. Veuillez patienter une minute avant de réessayer.'))
  })

  it('visits the page of the chosen result with Inertia and closes the palette', async () => {
    stubFetch(() => Promise.resolve(answer(200, { groups })))
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => {})
    const wrapper = await mountOpenSearch()
    await typeInPalette('hob')
    await vi.waitFor(() => expect(bodyText()).toContain('Hache'))

    const item = [...document.body.querySelectorAll<HTMLElement>('[data-slot="item"]')]
      .find((element) => element.textContent?.includes('Hache'))!
    item.click()

    await vi.waitFor(() => expect(visit).toHaveBeenCalledWith('/garage/tools/8'))
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })
})
