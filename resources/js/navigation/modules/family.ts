import type { NavigationEntry } from '../types'

/**
 * Every signed-in member (admins included) sees the library and the garage of
 * the others; the entry stays active on the page of each member (/family/…).
 */
export const familyNavigation: NavigationEntry[] = [
  { label: 'La famille', icon: 'i-lucide-users', route: 'family.index', requiredRole: 'user' },
]
