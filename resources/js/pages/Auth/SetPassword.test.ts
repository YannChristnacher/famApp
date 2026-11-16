import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import type { VisitOptions } from '@inertiajs/core'
import { mountWithUi } from '@/test/mountWithUi'
import SetPassword from './SetPassword.vue'

const props = {
  name: 'Paul Martin',
  email: 'paul@example.com',
  submitUrl: '/invitation/42?expires=1800000000&signature=abc',
}

describe('Auth/SetPassword', () => {
  it('welcomes the new member and asks for a confirmed password', async () => {
    const wrapper = await mountWithUi(SetPassword, { props })

    expect(wrapper.findComponent({ name: 'AuthLayout' }).props('title')).toBe('Définir mon mot de passe')
    expect(wrapper.findComponent({ name: 'AuthForm' }).exists()).toBe(true)
    const text = wrapper.text()
    expect(text).toContain('Bienvenue Paul Martin')
    expect(text).toContain('paul@example.com')
    expect(text).toContain('Mot de passe')
    expect(text).toContain('Confirmation du mot de passe')
    expect(text).toContain('Enregistrer mon mot de passe')
    expect(wrapper.find('input[name="password"]').exists()).toBe(true)
    expect(wrapper.find('input[name="password_confirmation"]').exists()).toBe(true)
  })

  it('posts the confirmed password to the signed invitation url', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountWithUi(SetPassword, { props })

    await wrapper.find('input[name="password"]').setValue('mon-nouveau-mot-de-passe')
    await wrapper.find('input[name="password_confirmation"]').setValue('mon-nouveau-mot-de-passe')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(props.submitUrl, {
      password: 'mon-nouveau-mot-de-passe',
      password_confirmation: 'mon-nouveau-mot-de-passe',
    }, expect.any(Object)))
  })

  it('shows the server error under the password field', async () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({ password: 'Le champ mot de passe doit contenir au moins 8 caractères.' })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountWithUi(SetPassword, { props })

    await wrapper.find('input[name="password"]').setValue('court')
    await wrapper.find('input[name="password_confirmation"]').setValue('court')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(wrapper.text()).toContain('Le champ mot de passe doit contenir au moins 8 caractères.'))
  })
})
