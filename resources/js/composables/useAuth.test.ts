import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { testPage, testUser } from '@/test/testPage'
import { rolesOf, useAuth } from './useAuth'

describe('rolesOf', () => {
  it('gives no role to guests', () => {
    expect(rolesOf(null)).toEqual([])
  })

  it('gives the user role to a regular user', () => {
    expect(rolesOf({ ...testUser, role: 'user' })).toEqual(['user'])
  })

  it('gives an admin both the admin and the user roles', () => {
    expect(rolesOf({ ...testUser, role: 'admin' })).toEqual(['admin', 'user'])
  })
})

describe('useAuth', () => {
  it('exposes the shared user and their roles', async () => {
    const { user, roles } = useAuth()
    expect(user.value).toBeNull()
    expect(roles.value).toEqual([])

    testPage.props.auth.user = { ...testUser, role: 'admin' }
    await nextTick()

    expect(user.value?.name).toBe('Marie Dupont')
    expect(roles.value).toEqual(['admin', 'user'])
  })
})
