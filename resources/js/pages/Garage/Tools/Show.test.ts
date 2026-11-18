import { describe, expect, it, vi } from 'vitest'
import type { VisitOptions } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { ToolDetails } from '@/types/garage'
import Show from './Show.vue'

const tool: ToolDetails = {
  id: 7,
  name: 'Perceuse',
  brand: { id: 3, name: 'Bosch' },
  thumbnail_url: '/storage/tools/front.jpg',
  shelves: [{ id: 4, name: 'Établi' }, { id: 2, name: 'Électroportatif' }],
  photos: [{ id: 11, url: '/storage/tools/front.jpg' }, { id: 12, url: '/storage/tools/side.jpg' }],
  accessories: [{ id: 1, name: 'Mallette' }, { id: 2, name: 'Batterie 18 V' }],
  comment: null,
}

const owner = { id: testUser.id, name: testUser.name }
const paul = { id: 2, name: 'Paul' }

/** The page as seen by the owner, unless `editable` is false (another member). */
async function mountPage(shown: ToolDetails = tool, editable = true) {
  testPage.props.auth.user = testUser

  return mountWithUi(Show, { props: { tool: shown, owner: editable ? owner : paul, editable }, attachTo: document.body })
}

function bodyButton(text: string): HTMLButtonElement | undefined {
  return Array.from(document.body.querySelectorAll('button')).find((button) => button.textContent?.trim() === text)
}

describe('Garage/Tools/Show', () => {
  it('shows the tool with its gallery in order, brand, accessories and shelves', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('title')).toBe('Perceuse')
    expect(wrapper.find('[data-testid="tool-brand"]').text()).toBe('Bosch')
    expect(wrapper.find('[data-testid="tool-gallery"]').findAll('img').map((img) => img.attributes('src'))).toEqual([
      '/storage/tools/front.jpg',
      '/storage/tools/side.jpg',
    ])
    expect(wrapper.findAll('[data-testid="tool-accessory"]').map((item) => item.text())).toEqual(['Mallette', 'Batterie 18 V'])
    expect(wrapper.findAll('[data-testid="tool-shelf"]').map((link) => [link.text(), link.attributes('href')])).toEqual([
      ['Établi', '/garage/tools?shelf=4'],
      ['Électroportatif', '/garage/tools?shelf=2'],
    ])
    expect(wrapper.find('a[href="/garage/tools/7/edit"]').text()).toContain('Modifier')
  })

  it('shows a tool without photo, brand, accessory nor shelf', async () => {
    const wrapper = await mountPage({ ...tool, brand: null, thumbnail_url: null, photos: [], accessories: [], shelves: [] })

    expect(wrapper.find('[data-testid="tool-gallery"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="thumbnail-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="tool-brand"]').text()).toBe('Marque non renseignée')
    expect(wrapper.text()).toContain('Aucun accessoire')
    expect(wrapper.text()).toContain('Rangé sur aucune étagère')
  })

  it('asks for a confirmation naming the tool before deleting it', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation((_url, options?: VisitOptions) => {
      options?.onSuccess?.({} as never)
    })
    const wrapper = await mountPage()

    await wrapper.findAll('button').find((button) => button.text() === 'Supprimer')!.trigger('click')
    await vi.waitFor(() => expect(document.body.textContent).toContain('Supprimer l’outil'))
    expect(document.body.textContent).toContain('L’outil « Perceuse », ses photos et ses accessoires seront supprimés définitivement.')
    expect(destroy).not.toHaveBeenCalled()

    const buttons = Array.from(document.body.querySelectorAll('button')).filter((button) => button.textContent?.trim() === 'Supprimer')
    buttons.at(-1)!.click()

    expect(destroy).toHaveBeenCalledWith('/garage/tools/7', expect.anything())
    await vi.waitFor(() => expect(document.body.textContent).not.toContain('Supprimer l’outil'))
  })

  it('cancels the deletion', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {})
    const wrapper = await mountPage()

    await wrapper.findAll('button').find((button) => button.text() === 'Supprimer')!.trigger('click')
    await vi.waitFor(() => expect(bodyButton('Annuler')).toBeDefined())
    bodyButton('Annuler')!.click()

    await vi.waitFor(() => expect(document.body.textContent).not.toContain('Supprimer l’outil'))
    expect(destroy).not.toHaveBeenCalled()
  })

  it('is read-only for another member: owner named, no action, shelves on the page of the member', async () => {
    const wrapper = await mountPage(tool, false)

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('breadcrumb')).toEqual([
      { label: 'La famille', to: '/family' },
      { label: 'Paul', to: '/family/2?tab=garage' },
      { label: 'Perceuse' },
    ])
    expect(wrapper.find('[data-testid="owner"]').text()).toBe('Dans le garage de Paul')
    expect(wrapper.findAll('[data-testid="tool-shelf"]').map((link) => link.attributes('href'))).toEqual([
      '/family/2?tab=garage&shelf=4',
      '/family/2?tab=garage&shelf=2',
    ])
    expect(wrapper.findAll('[data-testid="tool-accessory"]').map((item) => item.text())).toEqual(['Mallette', 'Batterie 18 V'])
    expect(wrapper.find('a[href="/garage/tools/7/edit"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Modifier')
    expect(wrapper.text()).not.toContain('Supprimer')
    expect(document.body.textContent).not.toContain('Supprimer l’outil')
  })

  it('shows the actions and the « Mes outils » breadcrumb to the owner', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('breadcrumb')).toEqual([
      { label: 'Garage' },
      { label: 'Mes outils', to: '/garage/tools' },
      { label: 'Perceuse' },
    ])
    expect(wrapper.find('[data-testid="owner"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/garage/tools/7/edit"]').text()).toContain('Modifier')
  })

  it('shows the comment as plain text, keeping its line breaks', async () => {
    const wrapper = await mountPage({ ...tool, comment: 'Première ligne\nSeconde ligne' }, false)
    const comment = wrapper.find('[data-testid="tool-comment"]')

    expect(comment.element.textContent).toBe('Première ligne\nSeconde ligne')
    expect(comment.classes()).toContain('whitespace-pre-line')
    expect(wrapper.text()).toContain('Commentaire')
  })

  it('shows a comment containing HTML literally', async () => {
    const wrapper = await mountPage({ ...tool, comment: '<b>gras</b><img src="x" onerror="alert(1)">' })
    const comment = wrapper.find('[data-testid="tool-comment"]')

    expect(comment.text()).toBe('<b>gras</b><img src="x" onerror="alert(1)">')
    expect(comment.find('b').exists()).toBe(false)
    expect(comment.find('img').exists()).toBe(false)
  })

  it('hides the comment block without comment', async () => {
    const wrapper = await mountPage({ ...tool, comment: null })

    expect(wrapper.find('[data-testid="tool-comment"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Commentaire')
  })
})
