import { describe, expect, it } from 'vitest'
import type { NavigationEntry } from './types'
import { filterEntriesByRoles, navigationRegistry, toNavigationMenuItems } from './registry'

const publicEntry: NavigationEntry = { label: 'Accueil', icon: 'i-lucide-house', route: 'home', requiredRole: null }
const adminEntry: NavigationEntry = { label: 'Administration', icon: 'i-lucide-shield', route: 'admin.index', requiredRole: 'admin' }
const userEntry: NavigationEntry = { label: 'Bibliothèque', icon: 'i-lucide-book', route: 'library.index', requiredRole: 'user' }

describe('navigationRegistry', () => {
  it('only declares the Core home entry for now', () => {
    expect(navigationRegistry).toEqual([
      { label: 'Accueil', icon: 'i-lucide-house', route: 'home', requiredRole: null },
    ])
  })
})

describe('filterEntriesByRoles', () => {
  const entries = [publicEntry, adminEntry, userEntry]

  it('keeps only entries without a required role when no role is given', () => {
    expect(filterEntriesByRoles(entries, [])).toEqual([publicEntry])
  })

  it('keeps entries whose required role is among the given roles', () => {
    expect(filterEntriesByRoles(entries, ['admin'])).toEqual([publicEntry, adminEntry])
    expect(filterEntriesByRoles(entries, ['user', 'admin'])).toEqual(entries)
  })

  it('ignores unknown roles', () => {
    expect(filterEntriesByRoles(entries, ['guest'])).toEqual([publicEntry])
  })
})

describe('toNavigationMenuItems', () => {
  it('maps entries to navigation menu items with resolved url and active state', () => {
    const items = toNavigationMenuItems([publicEntry, adminEntry], {
      url: (name) => `/resolved/${name}`,
      isActive: (name) => name === 'home',
    })

    expect(items).toEqual([
      { label: 'Accueil', icon: 'i-lucide-house', to: '/resolved/home', active: true },
      { label: 'Administration', icon: 'i-lucide-shield', to: '/resolved/admin.index', active: false },
    ])
  })
})
