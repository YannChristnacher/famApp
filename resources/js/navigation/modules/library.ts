import type { NavigationEntry } from '../types'

/**
 * Every signed-in member (admins included) has a library; the entry stays active
 * on all the pages of the module (/library/…).
 */
export const libraryNavigation: NavigationEntry[] = [
  { label: 'Bibliothèque', icon: 'i-lucide-library-big', route: 'library.index', requiredRole: 'user' },
]
