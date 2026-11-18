import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import type { Brand, ToolDetails, ToolShelf } from '@/types/garage'
import ToolForm from './ToolForm.vue'

const shelves: ToolShelf[] = [
  { id: 4, name: 'Électroportatif', position: 1 },
  { id: 9, name: 'Établi', position: 2 },
]

const brands: Brand[] = [{ id: 3, name: 'Bosch' }, { id: 5, name: 'Makita' }]

const tool: ToolDetails = {
  id: 7,
  name: 'Perceuse',
  brand: { id: 3, name: 'Bosch' },
  thumbnail_url: '/storage/tools/a.jpg',
  shelves: [{ id: 9, name: 'Établi' }],
  photos: [{ id: 11, url: '/storage/tools/a.jpg' }, { id: 12, url: '/storage/tools/b.jpg' }],
  accessories: [{ id: 1, name: 'Mallette' }, { id: 2, name: 'Batterie' }],
}

async function mountForm(props: { tool: ToolDetails | null }) {
  return mountWithUi(ToolForm, { props: { shelves, brands, ...props }, attachTo: document.body })
}

type FormWrapper = Awaited<ReturnType<typeof mountForm>>

function bodyOption(text: string): HTMLElement | undefined {
  return Array.from(document.body.querySelectorAll<HTMLElement>('[role="option"]'))
    .find((option) => option.textContent?.trim() === text)
}

async function waitForOption(text: string): Promise<HTMLElement> {
  return vi.waitFor(() => {
    const found = bodyOption(text)
    expect(found).toBeDefined()
    return found!
  })
}

async function typeBrand(wrapper: FormWrapper, typed: string): Promise<void> {
  await wrapper.find('[data-testid="brand-select"]').trigger('click')
  const search = await vi.waitFor(() => {
    const input = document.body.querySelector<HTMLInputElement>('input[placeholder="Rechercher ou créer…"]')
    expect(input).not.toBeNull()
    return input!
  })
  search.value = typed
  search.dispatchEvent(new Event('input', { bubbles: true }))
}

async function chooseShelf(wrapper: FormWrapper, name: string): Promise<void> {
  await wrapper.find('[data-testid="shelf-select"]').trigger('click')
  ;(await waitForOption(name)).click()
}

async function addPhotos(wrapper: FormWrapper, files: File[]): Promise<void> {
  const input = wrapper.find('input[type="file"]')
  Object.defineProperty(input.element, 'files', { value: files, configurable: true })
  await input.trigger('change')
}

function photoSources(wrapper: FormWrapper): (string | undefined)[] {
  return wrapper.findAll('[data-testid="photo-row"] img').map((img) => img.attributes('src'))
}

function accessoryInputs(wrapper: FormWrapper) {
  return wrapper.findAll('[data-testid="accessory-row"] input')
}

function spyOnPost() {
  return vi.spyOn(router, 'post').mockImplementation(() => {})
}

function postedData(post: ReturnType<typeof spyOnPost>): Record<string, unknown> {
  return post.mock.lastCall![1] as Record<string, unknown>
}

function photo(name: string): File {
  return new File([name], name, { type: 'image/jpeg' })
}

describe('Garage/ToolForm', () => {
  let objectUrls = 0

  // happy-dom has no object URLs: the photos are previewed with them.
  beforeEach(() => {
    objectUrls = 0
    URL.createObjectURL = vi.fn((file: Blob) => `blob:${(file as File).name}-${++objectUrls}`)
    URL.revokeObjectURL = vi.fn()
  })

  afterEach(() => {
    Reflect.deleteProperty(URL, 'createObjectURL')
    Reflect.deleteProperty(URL, 'revokeObjectURL')
  })

  it('posts a new tool with a brand created on the fly, ordered accessories, a shelf and ordered photos', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ tool: null })
    const face = photo('face.jpg')
    const side = photo('side.jpg')

    await wrapper.find('input[name="name"]').setValue('Scie plongeante')
    await typeBrand(wrapper, 'Festool')
    ;(await waitForOption('Créer "Festool"')).click()
    await vi.waitFor(() => expect(wrapper.find('[data-testid="brand-select"]').text()).toContain('Festool'))

    await wrapper.find('[data-testid="accessory-add"]').trigger('click')
    await wrapper.find('[data-testid="accessory-add"]').trigger('click')
    await wrapper.find('[data-testid="accessory-add"]').trigger('click')
    await accessoryInputs(wrapper)[0]!.setValue('Rail')
    await accessoryInputs(wrapper)[1]!.setValue('Lame')
    await accessoryInputs(wrapper)[2]!.setValue('À retirer')
    await wrapper.find('button[aria-label="Retirer l’accessoire 3"]').trigger('click')
    await wrapper.find('button[aria-label="Monter l’accessoire 2"]').trigger('click')

    await chooseShelf(wrapper, 'Établi')
    await addPhotos(wrapper, [face, side])
    await vi.waitFor(() => expect(photoSources(wrapper)).toEqual(['blob:face.jpg-1', 'blob:side.jpg-2']))
    expect(wrapper.find('[data-testid="photo-row"]').text()).toContain('Vignette')
    await wrapper.find('button[aria-label="Avancer la photo 2"]').trigger('click')
    expect(photoSources(wrapper)).toEqual(['blob:side.jpg-2', 'blob:face.jpg-1'])

    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith('/garage/tools', expect.anything(), expect.objectContaining({ preserveScroll: true })))
    expect(postedData(post)).toEqual({
      name: 'Scie plongeante',
      brand: 'Festool',
      shelf_ids: [9],
      accessories: ['Lame', 'Rail'],
      photos: [{ file: side }, { file: face }],
    })
    // The files are sent as they are, not as reactive proxies.
    const sent = postedData(post).photos as { file: File }[]
    expect(sent[0]!.file).toBe(side)
  })

  it('selects an existing brand rather than creating a duplicate differing by case or spaces', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ tool: null })

    await wrapper.find('input[name="name"]').setValue('Meuleuse')
    wrapper.findComponent({ name: 'SelectMenu' }).vm.$emit('create', '  MAKITA ')
    await vi.waitFor(() => expect(wrapper.find('[data-testid="brand-select"]').text()).toContain('Makita'))
    expect(wrapper.findComponent({ name: 'SelectMenu' }).props('items')).toEqual(['Bosch', 'Makita'])

    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(post).toHaveBeenCalled())
    expect(postedData(post)).toMatchObject({ brand: 'Makita' })
  })

  it('chooses an existing brand in the menu', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ tool: null })

    await wrapper.find('input[name="name"]').setValue('Ponceuse')
    await wrapper.find('[data-testid="brand-select"]').trigger('click')
    ;(await waitForOption('Bosch')).click()
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalled())
    expect(postedData(post)).toEqual({ name: 'Ponceuse', brand: 'Bosch', shelf_ids: [], accessories: [], photos: [] })
  })

  it('edits a tool: keeps, removes, adds and reorders photos, sent with POST and _method=patch', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ tool })
    const added = photo('new.jpg')

    expect((wrapper.find('input[name="name"]').element as HTMLInputElement).value).toBe('Perceuse')
    expect(wrapper.find('[data-testid="brand-select"]').text()).toContain('Bosch')
    expect(accessoryInputs(wrapper).map((input) => (input.element as HTMLInputElement).value)).toEqual(['Mallette', 'Batterie'])
    expect(photoSources(wrapper)).toEqual(['/storage/tools/a.jpg', '/storage/tools/b.jpg'])

    await wrapper.find('button[aria-label="Retirer la photo 1"]').trigger('click')
    await addPhotos(wrapper, [added])
    await vi.waitFor(() => expect(photoSources(wrapper)).toEqual(['/storage/tools/b.jpg', 'blob:new.jpg-1']))
    await wrapper.find('button[aria-label="Avancer la photo 2"]').trigger('click')
    expect(URL.revokeObjectURL).not.toHaveBeenCalled()

    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith('/garage/tools/7', expect.anything(), expect.anything()))
    expect(postedData(post)).toEqual({
      name: 'Perceuse',
      brand: 'Bosch',
      shelf_ids: [9],
      accessories: ['Mallette', 'Batterie'],
      photos: [{ file: added }, { id: 12 }],
      _method: 'patch',
    })
  })

  it('removes the brand and revokes the preview of a removed new photo', async () => {
    const post = spyOnPost()
    const wrapper = await mountForm({ tool })

    wrapper.findComponent({ name: 'SelectMenu' }).vm.$emit('update:modelValue', null)
    await addPhotos(wrapper, [photo('new.jpg')])
    await vi.waitFor(() => expect(photoSources(wrapper)).toHaveLength(3))
    await wrapper.find('button[aria-label="Retirer la photo 3"]').trigger('click')
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:new.jpg-1')

    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(post).toHaveBeenCalled())
    expect(postedData(post)).toMatchObject({ brand: null, photos: [{ id: 11 }, { id: 12 }] })
  })

  it('keeps at most 8 photos and warns about the ignored ones', async () => {
    const wrapper = await mountForm({ tool })
    const files = Array.from({ length: 7 }, (_, index) => photo(`${index}.jpg`))

    await addPhotos(wrapper, files)

    await vi.waitFor(() => expect(photoSources(wrapper)).toHaveLength(8))
    expect(wrapper.find('[data-testid="photos-too-many"]').exists()).toBe(true)
    expect(wrapper.find('input[type="file"]').exists()).toBe(false)
  })

  it('shows the server errors of the accessories, photos and shelves', async () => {
    const wrapper = await mountForm({ tool })
    const form = wrapper.vm as unknown as { $: { setupState: { form: { setError: (errors: Record<string, string>) => void } } } }

    form.$.setupState.form.setError({
      'accessories.1': 'Le champ accessoire est obligatoire.',
      'photos.1.file': 'La photo ne doit pas dépasser 6000 pixels de côté.',
      'shelf_ids.0': 'Une des étagères choisies n’existe pas.',
    })

    await vi.waitFor(() => expect(wrapper.text()).toContain('Le champ accessoire est obligatoire.'))
    expect(wrapper.findAll('[data-testid="accessory-row"]')[1]!.text()).toContain('Le champ accessoire est obligatoire.')
    expect(wrapper.text()).toContain('La photo ne doit pas dépasser 6000 pixels de côté.')
    expect(wrapper.text()).toContain('Une des étagères choisies n’existe pas.')
  })
})
