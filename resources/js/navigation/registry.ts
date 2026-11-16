import type { NavigationMenuItem } from '@nuxt/ui'
import type { NavigationEntry, NavigationResolvers } from './types'
import { coreNavigation } from './modules/core'

/**
 * Every module declares its entries in `modules/<module>.ts` and adds them here.
 */
export const navigationRegistry: NavigationEntry[] = [
  ...coreNavigation,
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
