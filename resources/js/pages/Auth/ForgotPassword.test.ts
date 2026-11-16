import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage } from '@/test/testPage'
import ForgotPassword from './ForgotPassword.vue'

describe('Auth/ForgotPassword', () => {
  it('renders a French form with a link back to the login page', async () => {
    const wrapper = await mountWithUi(ForgotPassword)

    expect(wrapper.findComponent({ name: 'AuthLayout' }).props('title')).toBe('Mot de passe oublié')
    expect(wrapper.text()).toContain('Adresse email')
    expect(wrapper.text()).toContain('Envoyer le lien')
    expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
  })

  it('posts the email to the password.email route', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountWithUi(ForgotPassword)

    await wrapper.find('input[name="email"]').setValue('marie@example.com')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith('/forgot-password', { email: 'marie@example.com' }, expect.any(Object)))
  })

  it('shows the neutral status message', async () => {
    testPage.props.status = 'Si un compte correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé.'
    const wrapper = await mountWithUi(ForgotPassword)

    expect(wrapper.findComponent({ name: 'Alert' }).text()).toContain('Si un compte correspond à cette adresse')
  })
})
