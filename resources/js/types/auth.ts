export type Role = 'admin' | 'user'

/**
 * The authenticated user shared with every Inertia page (HandleInertiaRequests).
 */
export interface SharedUser {
  id: number
  name: string
  email: string
  role: Role
}
