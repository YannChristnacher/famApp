import type { NavigationEntry } from '../types'

export const adminNavigation: NavigationEntry[] = [
  { label: 'Contrôle', icon: 'i-lucide-activity', route: 'admin.control', requiredRole: 'admin' },
  { label: 'Demandes de compte', icon: 'i-lucide-inbox', route: 'admin.account-requests.index', requiredRole: 'admin' },
]
