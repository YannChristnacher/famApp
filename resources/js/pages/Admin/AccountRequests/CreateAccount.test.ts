import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import type { VisitOptions } from '@inertiajs/core'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import CreateAccount from './CreateAccount.vue'

const props = {
  accountRequest: { id: 7, name: 'Paul Martin', email: 'paul@example.com', message: 'Je suis le cousin de Marie.' },
  roles: ['user', 'admin'] as const,
}

async function mountPage() {
  testPage.props.auth.user = { ...testUser, role: 'admin' }

  return mountWithUi(CreateAccount, { props: { ...props, roles: [...props.roles] } })
}

function input(wrapper: Awaited<ReturnType<typeof mountPage>>, name: string) {
  return wrapper.find(`input[name="${name}"]`).element as HTMLInputElement
}

describe('Admin/AccountRequests/CreateAccount', () => {
  it('prefills the form with the request and shows the message of the requester', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('title')).toBe('Créer le compte')
    const text = wrapper.text()
    expect(text).toContain('Nom')
    expect(text).toContain('Adresse email')
    expect(text).toContain('Rôle')
    expect(text).toContain('Je suis le cousin de Marie.')
    expect(input(wrapper, 'name').value).toBe('Paul Martin')
    expect(input(wrapper, 'email').value).toBe('paul@example.com')
    const select = wrapper.findComponent({ name: 'Select' })
    expect(select.props('modelValue')).toBe('user')
    expect(select.props('items')).toEqual([
      { label: 'Membre', value: 'user' },
      { label: 'Administrateur', value: 'admin' },
    ])
    expect(wrapper.find('a[href="/admin/account-requests"]').text()).toBe('Annuler')
  })

  it('posts the edited name, email and role to the account creation route', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountPage()

    await wrapper.find('input[name="name"]').setValue('Paul M.')
    await wrapper.find('input[name="email"]').setValue('paul.m@example.com')
    wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'admin')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(
      '/admin/account-requests/7/account',
      { name: 'Paul M.', email: 'paul.m@example.com', role: 'admin' },
      expect.any(Object),
    ))
  })

  it('shows the server errors under the matching fields', async () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({
        name: 'Le champ nom est obligatoire.',
        email: 'La valeur du champ adresse e-mail est déjà utilisée.',
        role: 'Le champ rôle sélectionné est invalide.',
      })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountPage()

    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => {
      const fields = wrapper.findAllComponents({ name: 'FormField' })
      const fieldError = (name: string) => fields.find((field) => field.props('name') === name)?.props('error')
      expect(fieldError('name')).toBe('Le champ nom est obligatoire.')
      expect(fieldError('email')).toBe('La valeur du champ adresse e-mail est déjà utilisée.')
      expect(fieldError('role')).toBe('Le champ rôle sélectionné est invalide.')
    })
  })
})
