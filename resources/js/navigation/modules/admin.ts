import type { NavigationEntry } from '../types'

export const adminNavigation: NavigationEntry[] = [
  { label: 'Demandes de compte', icon: 'i-lucide-inbox', route: 'admin.account-requests.index', requiredRole: 'admin' },
]
