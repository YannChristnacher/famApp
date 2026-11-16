import { describe, expect, it, vi } from 'vitest'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import ResetPassword from './ResetPassword.vue'

const props = { token: 'jeton-123', email: 'marie@example.com' }

describe('Auth/ResetPassword', () => {
  it('renders a French form prefilled with the email', async () => {
    const wrapper = await mountWithUi(ResetPassword, { props })

    expect(wrapper.findComponent({ name: 'AuthLayout' }).props('title')).toBe('Nouveau mot de passe')
    expect(wrapper.text()).toContain('Confirmation du mot de passe')
    expect((wrapper.find('input[name="email"]').element as HTMLInputElement).value).toBe('marie@example.com')
  })

  it('posts the token, the email and the confirmed password', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountWithUi(ResetPassword, { props })

    await wrapper.find('input[name="password"]').setValue('nouveau-mot-de-passe')
    await wrapper.find('input[name="password_confirmation"]').setValue('nouveau-mot-de-passe')
    await wrapper.find('form').trigger('submit')

    await vi.waitFor(() => expect(post).toHaveBeenCalledWith('/reset-password', {
      token: 'jeton-123',
      email: 'marie@example.com',
      password: 'nouveau-mot-de-passe',
      password_confirmation: 'nouveau-mot-de-passe',
    }, expect.any(Object)))
  })
})
