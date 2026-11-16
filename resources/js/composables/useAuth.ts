import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { Role, SharedUser } from '@/types/auth'

/**
 * An admin is also a family member, so they see the entries of both roles.
 */
export function rolesOf(user: SharedUser | null): Role[] {
  if (user === null) {
    return []
  }

  return user.role === 'admin' ? ['admin', 'user'] : ['user']
}

export function useAuth() {
  const page = usePage()
  const user = computed(() => page.props.auth.user)
  const roles = computed(() => rolesOf(user.value))

  return { user, roles }
}
