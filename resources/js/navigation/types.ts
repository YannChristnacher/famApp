/**
 * A sidebar entry declared by a module in the navigation registry.
 */
export interface NavigationEntry {
  label: string
  icon: string
  /** Laravel route name, resolved to a URL when the menu is built. */
  route: string
  /** Role a user must have to see the entry; null means visible to everyone. */
  requiredRole: string | null
}

export interface NavigationResolvers {
  url: (routeName: string) => string
  isActive: (routeName: string) => boolean
}
