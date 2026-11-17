import { describe, expect, it, vi } from 'vitest'
import type { VisitOptions } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { ToolShelf } from '@/types/garage'
import Index from './Index.vue'

const shelves: ToolShelf[] = [
  { id: 4, name: 'Électroportatif', position: 1 },
  { id: 9, name: 'Manuel', position: 2 },
  { id: 2, name: 'Jardin', position: 3 },
]

async function mountPage(items: ToolShelf[] = shelves) {
  testPage.props.auth.user = testUser

  return mountWithUi(Index, { props: { shelves: items }, attachTo: document.body })
}

type PageWrapper = Awaited<ReturnType<typeof mountPage>>

function rows(wrapper: PageWrapper) {
  return wrapper.findAll('[data-testid="tool-shelf"]')
}

function rowButton(wrapper: PageWrapper, index: number, label: string) {
  return rows(wrapper)[index]!.find(`button[aria-label^="${label}"]`)
}

function bodyButton(text: string): HTMLButtonElement | undefined {
  return Array.from(document.body.querySelectorAll('button')).find((button) => button.textContent?.trim() === text)
}

describe('Garage/Shelves/Index', () => {
  it('renders the shelves in the dashboard layout, in the given order', async () => {
    const wrapper = await mountPage()

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.props('title')).toBe('Mon garage')
    expect(layout.props('breadcrumb')).toEqual([{ label: 'Garage' }, { label: 'Étagères' }])
    expect(rows(wrapper).map((row) => row.text())).toEqual([
      expect.stringContaining('Électroportatif'),
      expect.stringContaining('Manuel'),
      expect.stringContaining('Jardin'),
    ])
    expect(wrapper.text()).not.toContain('Votre garage est vide.')
  })

  it('invites to create the first shelf when the garage is empty', async () => {
    const wrapper = await mountPage([])

    expect(rows(wrapper)).toHaveLength(0)
    expect(wrapper.findComponent({ name: 'Empty' }).exists()).toBe(true)
    expect(wrapper.text()).toContain('Votre garage est vide.')
    expect(wrapper.text()).toContain('Créez votre première étagère')
    expect(wrapper.find('input[name="name"]').exists()).toBe(true)
  })

  it('posts the name of a new shelf and empties the field on success', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onSuccess?.({} as never)
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountPage()

    await wrapper.find('input[name="name"]').setValue('Outils à main')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(
      '/garage/shelves',
      { name: 'Outils à main' },
      expect.objectContaining({ preserveScroll: true }),
    ))
    await vi.waitFor(() => expect((wrapper.find('input[name="name"]').element as HTMLInputElement).value).toBe(''))
  })

  it('shows the server error under the name of the new shelf', async () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({ name: 'Vous avez déjà une étagère nommée « Électroportatif ».' })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountPage()

    await wrapper.find('input[name="name"]').setValue('électroportatif')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(wrapper.text()).toContain('Vous avez déjà une étagère nommée « Électroportatif ».'))
  })

  it('renames a shelf in a modal prefilled with its name', async () => {
    const patch = vi.spyOn(router, 'patch').mockImplementation(() => {})
    const wrapper = await mountPage()

    await rowButton(wrapper, 1, 'Renommer').trigger('click')

    const input = await vi.waitFor(() => {
      const found = document.body.querySelector<HTMLInputElement>('input[name="rename"]')
      expect(found).not.toBeNull()
      return found!
    })
    expect(document.body.textContent).toContain("Renommer l'étagère")
    expect(input.value).toBe('Manuel')

    input.value = 'Outils à main'
    input.dispatchEvent(new Event('input'))
    bodyButton('Renommer')!.click()

    await vi.waitFor(() => expect(patch).toHaveBeenCalledWith(
      '/garage/shelves/9',
      { name: 'Outils à main' },
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
      const modal = wrapper.findComponent({ name: 'ToolShelfRenameModal' })
      expect(modal.findComponent({ name: 'FormField' }).props('error')).toBe('Le champ nom est obligatoire.')
    })
  })

  it('asks for a confirmation before deleting a shelf', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {})
    const wrapper = await mountPage()

    await rowButton(wrapper, 2, 'Supprimer').trigger('click')
    await vi.waitFor(() => expect(document.body.textContent).toContain("Supprimer l'étagère"))
    expect(document.body.textContent).toContain('« Jardin »')

    bodyButton('Annuler')!.click()
    await vi.waitFor(() => expect(document.body.textContent).not.toContain("Supprimer l'étagère"))
    expect(destroy).not.toHaveBeenCalled()

    await rowButton(wrapper, 2, 'Supprimer').trigger('click')
    await vi.waitFor(() => expect(bodyButton('Supprimer')).toBeDefined())
    bodyButton('Supprimer')!.click()

    await vi.waitFor(() => expect(destroy).toHaveBeenCalledWith(
      '/garage/shelves/2',
      expect.objectContaining({ preserveScroll: true }),
    ))
  })

  it('disables moving the first shelf up and the last one down', async () => {
    const wrapper = await mountPage()

    expect(rowButton(wrapper, 0, 'Monter').attributes('disabled')).toBeDefined()
    expect(rowButton(wrapper, 0, 'Descendre').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 1, 'Monter').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 1, 'Descendre').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 2, 'Monter').attributes('disabled')).toBeUndefined()
    expect(rowButton(wrapper, 2, 'Descendre').attributes('disabled')).toBeDefined()
  })

  it('disables both moves of a single shelf', async () => {
    const wrapper = await mountPage([{ id: 4, name: 'Électroportatif', position: 1 }])

    expect(rowButton(wrapper, 0, 'Monter').attributes('disabled')).toBeDefined()
    expect(rowButton(wrapper, 0, 'Descendre').attributes('disabled')).toBeDefined()
  })

  it('moves a shelf up or down and blocks the moves until the request finishes', async () => {
    let options: VisitOptions | undefined
    const patch = vi.spyOn(router, 'patch').mockImplementation((_url, _data, visitOptions?: VisitOptions) => {
      options = visitOptions
      options?.onStart?.({} as never)
    })
    const wrapper = await mountPage()

    await rowButton(wrapper, 1, 'Monter').trigger('click')
    expect(patch).toHaveBeenCalledWith(
      '/garage/shelves/9/move',
      { direction: 'up' },
      expect.objectContaining({ preserveScroll: true }),
    )
    expect(rowButton(wrapper, 1, 'Descendre').attributes('disabled')).toBeDefined()

    options?.onFinish?.({} as never)
    await wrapper.vm.$nextTick()
    await rowButton(wrapper, 0, 'Descendre').trigger('click')
    expect(patch).toHaveBeenLastCalledWith('/garage/shelves/4/move', { direction: 'down' }, expect.any(Object))
  })
})
