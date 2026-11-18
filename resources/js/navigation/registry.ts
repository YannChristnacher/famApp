import type { NavigationMenuItem } from '@nuxt/ui'
import type { NavigationEntry, NavigationResolvers } from './types'
import { adminNavigation } from './modules/admin'
import { coreNavigation } from './modules/core'
import { familyNavigation } from './modules/family'
import { garageNavigation } from './modules/garage'
import { libraryNavigation } from './modules/library'

/**
 * Every module declares its entries in `modules/<module>.ts` and adds them here.
 */
export const navigationRegistry: NavigationEntry[] = [
  ...coreNavigation,
  ...libraryNavigation,
  ...garageNavigation,
  ...familyNavigation,
  ...adminNavigation,
]

export function filterEntriesByRoles(entries: NavigationEntry[], roles: string[]): NavigationEntry[] {
  return entries.filter((entry) => entry.requiredRole === null || roles.includes(entry.requiredRole))
}

export function toNavigationMenuItems(
  entries: NavigationEntry[],
  resolvers: NavigationResolvers,
): NavigationMenuItem[] {
  return entries.map((entry) => ({
    label: entry.label,
    icon: entry.icon,
    to: resolvers.url(entry.route),
    active: resolvers.isActive(entry.route),
  }))
}

/**
 * An entry is active on its own url and on its sub-pages; the root url only matches itself.
 */
export function isActiveUrl(currentUrl: string, entryUrl: string): boolean {
  const currentPath = currentUrl.split(/[?#]/)[0] ?? ''

  if (entryUrl === '/') {
    return currentPath === '/'
  }

  return currentPath === entryUrl || currentPath.startsWith(`${entryUrl}/`)
}
