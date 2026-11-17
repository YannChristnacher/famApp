import type { NavigationEntry } from '../types'

/**
 * Every signed-in member (admins included) has a garage; the entry stays active
 * on all the pages of the module (/garage/…).
 */
export const garageNavigation: NavigationEntry[] = [
  { label: 'Garage', icon: 'i-lucide-warehouse', route: 'garage.index', requiredRole: 'user' },
]
