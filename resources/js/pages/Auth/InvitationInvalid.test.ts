import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import InvitationInvalid from './InvitationInvalid.vue'

describe('Auth/InvitationInvalid', () => {
  it('explains why the link cannot be used and links to the forgotten password and login pages', async () => {
    const message = 'Ce lien pour définir votre mot de passe a expiré ou n\'est pas valide.'
    const wrapper = await mountWithUi(InvitationInvalid, { props: { message } })

    expect(wrapper.findComponent({ name: 'AuthLayout' }).props('title')).toBe('Lien inutilisable')
    expect(wrapper.findComponent({ name: 'Alert' }).props('title')).toBe(message)
    expect(wrapper.find('a[href="/forgot-password"]').text()).toBe('Mot de passe oublié')
    expect(wrapper.find('a[href="/login"]').text()).toBe('Se connecter')
  })
})
