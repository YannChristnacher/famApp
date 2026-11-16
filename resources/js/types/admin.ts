import type { Role } from './auth'

export type AccountRequestStatus = 'pending' | 'approved' | 'rejected'

/**
 * An account request as listed on the admin page (AccountRequestResource).
 */
export interface AccountRequestRow {
  id: number
  name: string
  email: string
  message: string | null
  status: AccountRequestStatus
  /** ISO 8601 date. */
  createdAt: string | null
}

/**
 * The pending request the account form is prefilled with.
 */
export interface AccountRequestDraft {
  id: number
  name: string
  email: string
  message: string | null
}

export interface AccountData {
  name: string
  email: string
  role: Role
}
