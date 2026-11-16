import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import type { VisitOptions } from '@inertiajs/core'
import { mountWithUi } from '@/test/mountWithUi'
import RequestAccount from './RequestAccount.vue'

describe('Auth/RequestAccount', () => {
  it('renders a French form with a name, an email, a message and a link back to the login page', async () => {
    const wrapper = await mountWithUi(RequestAccount)

    expect(wrapper.findComponent({ name: 'AuthLayout' }).props('title')).toBe('Demander un compte')
    expect(wrapper.findComponent({ name: 'AuthForm' }).exists()).toBe(true)
    const text = wrapper.text()
    expect(text).toContain('Nom')
    expect(text).toContain('Adresse email')
    expect(text).toContain('Message')
    expect(text).toContain('Envoyer la demande')
    expect(wrapper.find('input[name="name"]').exists()).toBe(true)
    expect(wrapper.find('input[name="email"]').exists()).toBe(true)
    expect(wrapper.findComponent({ name: 'Textarea' }).find('textarea[name="message"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/login"]').text()).toBe('Retour à la connexion')
  })

  it('posts the name, the email and the message to the account-request.store route', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountWithUi(RequestAccount)

    await wrapper.find('input[name="name"]').setValue('Paul Martin')
    await wrapper.find('input[name="email"]').setValue('paul@example.com')
    await wrapper.find('textarea[name="message"]').setValue('Je suis le cousin de Marie.')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(
      '/account-request',
      { name: 'Paul Martin', email: 'paul@example.com', message: 'Je suis le cousin de Marie.' },
      expect.any(Object),
    ))
  })

  it('sends an empty message when none was typed', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountWithUi(RequestAccount)

    await wrapper.find('input[name="name"]').setValue('Paul Martin')
    await wrapper.find('input[name="email"]').setValue('paul@example.com')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(
      '/account-request',
      { name: 'Paul Martin', email: 'paul@example.com', message: '' },
      expect.any(Object),
    ))
  })

  it('shows the server errors under the matching fields', async () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({
        name: 'Le champ nom est obligatoire.',
        email: 'Trop de demandes envoyées. Veuillez réessayer plus tard.',
        message: 'Le texte de message ne peut contenir plus de 2000 caractères.',
      })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountWithUi(RequestAccount)

    await wrapper.find('input[name="name"]').setValue('x')
    await wrapper.find('input[name="email"]').setValue('paul@example.com')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => {
      const fields = wrapper.findAllComponents({ name: 'FormField' })
      const fieldError = (name: string) => fields.find((field) => field.props('name') === name)?.props('error')
      expect(fieldError('name')).toBe('Le champ nom est obligatoire.')
      expect(fieldError('email')).toBe('Trop de demandes envoyées. Veuillez réessayer plus tard.')
      expect(fieldError('message')).toBe('Le texte de message ne peut contenir plus de 2000 caractères.')
    })
    expect(wrapper.text()).toContain('Le texte de message ne peut contenir plus de 2000 caractères.')
  })
})
