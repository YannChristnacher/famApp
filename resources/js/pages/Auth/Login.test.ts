import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import type { VisitOptions } from '@inertiajs/core'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage } from '@/test/testPage'
import Login from './Login.vue'

describe('Auth/Login', () => {
  it('renders a French authentication form', async () => {
    const wrapper = await mountWithUi(Login)

    expect(wrapper.findComponent({ name: 'AuthLayout' }).props('title')).toBe('Connexion')
    expect(wrapper.findComponent({ name: 'AuthForm' }).exists()).toBe(true)
    const text = wrapper.text()
    expect(text).toContain('Adresse email')
    expect(text).toContain('Mot de passe')
    expect(text).toContain('Se souvenir de moi')
    expect(text).toContain('Se connecter')
    expect(wrapper.find('a[href="/forgot-password"]').text()).toBe('Mot de passe oublié ?')
  })

  it('posts the credentials and shows the server error under the email field', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation((_url, _data, options?: VisitOptions) => {
      options?.onError?.({ email: 'Ces identifiants ne correspondent pas à nos enregistrements.' })
      options?.onFinish?.({} as never)
    })
    const wrapper = await mountWithUi(Login)

    await wrapper.find('input[name="email"]').setValue('marie@example.com')
    await wrapper.find('input[name="password"]').setValue('mauvais')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith(
      '/login',
      { email: 'marie@example.com', password: 'mauvais', remember: false },
      expect.any(Object),
    ))
    await vi.waitFor(() => expect(wrapper.text()).toContain('Ces identifiants ne correspondent pas à nos enregistrements.'))
  })

  it('shows the status message shared by the server', async () => {
    testPage.props.status = 'Votre mot de passe a été réinitialisé !'
    const wrapper = await mountWithUi(Login)

    expect(wrapper.findComponent({ name: 'Alert' }).text()).toContain('Votre mot de passe a été réinitialisé !')
  })
})
