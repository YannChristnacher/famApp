import { describe, expect, it, vi } from 'vitest'
import type { VisitOptions } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { Bookshelf } from '@/types/library'
import Index from './Index.vue'

const bookshelves: Bookshelf[] = [
  { id: 4, name: 'Fantasy', position: 1 },
  { id: 9, name: 'Polars', position: 2 },
  { id: 2, name: 'Coup de cœur', position: 3 },
]

async function mountPage(items: Bookshelf[] = bookshelves) {
  testPage.props.auth.user = testUser

  return mountWithUi(Index, { props: { bookshelves: items }, attachTo: document.body })
}

type PageWrapper = Awaited<ReturnType<typeof mountPage>>

function rows(wrapper: PageWrapper) {
  return wrapper.findAll('[data-testid="bookshelf"]')
}

function rowButton(wrapper: PageWrapper, index: number, label: string) {
  return rows(wrapper)[index]!.find(`button[aria-label^="${label}"]`)
}

function bodyButton(text: string): HTMLButtonElement | undefined {
  return Array.from(document.body.querySelectorAll('button')).find((button) => button.textContent?.trim() === text)
}

describe('Library/Bookshelves/Index', () => {
  it('renders the bookshelves in the dashboard layout, in the given order', async () => {
    const wrapper = await mountPage()

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.props('title')).toBe('Ma bibliothèque')
    expect(layout.props('breadcrumb')).toEqual([{ label: 'Bibliothèque' }, { label: 'Étagères' }])
    expect(rows(wrapper).map((row) => row.text())).toEqual([
      expect.stringContaining('Fantasy'),
      expect.stringContaining('Polars'),
      expect.stringContaining('Coup de cœur'),
    ])
    expect(wrapper.text()).not.toContain('Votre bibliothèque est vide.')
  })

  it('invites to create the first bookshelf when the library is empty', async () => {
    const wrapper = await mountPage([])

    expect(rows(wrapper)).toHaveLength(0)
    expect(wrapper.findComponent({ name: 'Empty' }).exists()).toBe(true)
    expect(wrapper.text()).toContain('Votre bibliothèque est vide.')
    expect(wrapper.text()).toContain('Créez votre première étagère')
    expect(wrapper.find('input[name="name"]').exists()).toBe(true)
  })

  it('posts the name of a new bookshelf and empties the field on success', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onSuccess?.({} as never)
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountPage()

    await wrapper.find('input[name="name"]').setValue('Romans noirs')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(
      '/library/bookshelves',
      { name: 'Romans noirs' },
      expect.objectContaining({ preserveScroll: true }),
    ))
    await vi.waitFor(() => expect((wrapper.find('input[name="name"]').element as HTMLInputElement).value).toBe(''))
  })

  it('shows the server error under the name of the new bookshelf', async () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({ name: 'Vous avez déjà une étagère nommée « Fantasy ».' })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountPage()

    await wrapper.find('input[name="name"]').setValue('fantasy')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(wrapper.text()).toContain('Vous avez déjà une étagère nommée « Fantasy ».'))
  })

  it('renames a bookshelf in a modal prefilled with its name', async () => {
    const patch = vi.spyOn(router, 'patch').mockImplementation(() => {})
    const wrapper = await mountPage()

    await rowButton(wrapper, 1, 'Renommer').trigger('click')

    const input = await vi.waitFor(() => {
      const found = document.body.querySelector<HTMLInputElement>('input[name="rename"]')
      expect(found).not.toBeNull()
      return found!
    })
    expect(document.body.textContent).toContain("Renommer l'étagère")
    expect(input.value).toBe('Polars')

    input.value = 'Romans noirs'
    input.dispatchEvent(new Event('input'))
    bodyButton('Renommer')!.click()

    await vi.waitFor(() => expect(patch).toHaveBeenCalledWith(
      '/library/bookshelves/9',
      { name: 'Romans noirs' },
      expect.objectContaining({ preserveScroll: true }),
    ))
  })

  it('shows the renaming errors in the modal', async () => {
    vi.spyOn(router, 'patch').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({ name: 'Le champ nom est obligatoire.' })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountPage()

    await rowButton(wrapper, 0, 'Renommer').trigger('click')
    await vi.waitFor(() => expect(bodyButton('Renommer')).toBeDefined())
    bodyButton('Renommer')!.click()

    await vi.waitFor(() => {
      const modal = wrapper.findComponent({ name: 'BookshelfRenameModal' })
      expect(modal.findComponent({ name: 'FormField' }).props('error')).toBe('Le champ nom est obligatoire.')
    })
  })

  it('asks for a confirmation before deleting a bookshelf', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {})
    const wrapper = await mountPage()

    await rowButton(wrapper, 2, 'Supprimer').trigger('click')
    await vi.waitFor(() => expect(document.body.textContent).toContain("Supprimer l'étagère"))
    expect(document.body.textContent).toContain('« Coup de cœur »')

    bodyButton('Annuler')!.click()
    await vi.waitFor(() => expect(document.body.textContent).not.toContain("Supprimer l'étagère"))
    expect(destroy).not.toHaveBeenCalled()

    await rowButton(wrapper, 2, 'Supprimer').trigger('click')
    await vi.waitFor(() => expect(bodyButton('Supprimer')).toBeDefined())
    bodyButton('Supprimer')!.click()

    await vi.waitFor(() => expect(destroy).toHaveBeenCalledWith(
      '/library/bookshelves/2',
      expect.objectContaining({ preserveScroll: true }),
    ))
  })

  it('disables moving the first bookshelf up and the last one down', async () => {
    const wrapper = await mountPage()

    expect(rowButton(wrapper, 0, 'Monter').attributes('disabled')).toBeDefined()
    expect(rowButton(wrapper, 0, 'Descendre').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 1, 'Monter').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 1, 'Descendre').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 2, 'Monter').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 2, 'Descendre').attributes('disabled')).toBeDefined()
  })

  it('disables both moves of a single bookshelf', async () => {
    const wrapper = await mountPage([{ id: 4, name: 'Fantasy', position: 1 }])

    expect(rowButton(wrapper, 0, 'Monter').attributes('disabled')).toBeDefined()
    expect(rowButton(wrapper, 0, 'Descendre').attributes('disabled')).toBeDefined()
  })

  it('moves a bookshelf up or down and blocks the moves until the request finishes', async () => {
    let options: VisitOptions | undefined
    const patch = vi.spyOn(router, 'patch').mockImplementation((_url, _data, visitOptions?: VisitOptions) => {
      options = visitOptions
      options?.onStart?.({} as never)
    })
    const wrapper = await mountPage()

    await rowButton(wrapper, 1, 'Monter').trigger('click')
    expect(patch).toHaveBeenCalledWith(
      '/library/bookshelves/9/move',
      { direction: 'up' },
      expect.objectContaining({ preserveScroll: true }),
    )
    expect(rowButton(wrapper, 1, 'Descendre').attributes('disabled')).toBeDefined()

    options?.onFinish?.({} as never)
    await wrapper.vm.$nextTick()
    await rowButton(wrapper, 0, 'Descendre').trigger('click')
    expect(patch).toHaveBeenLastCalledWith('/library/bookshelves/4/move', { direction: 'down' }, expect.any(Object))
  })
})
