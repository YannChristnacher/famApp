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

/**
 * Figures of the admin control page (ControlCountsService).
 */
export interface ControlCounts {
  members: number
  invited: number
  books: number
  tools: number
  bookshelves: number
  toolShelves: number
  brands: number
}

/** A book or a tool among the latest additions (RecentAdditionResource). */
export interface RecentAddition {
  type: 'book' | 'tool'
  id: number
  label: string
  owner: string
  /** ISO 8601 date. */
  createdAt: string | null
  /** Relative URL of the book or tool page. */
  url: string
}

/** A member's last sign-in (RecentLoginResource). */
export interface RecentLogin {
  id: number
  name: string
  /** ISO 8601 date. */
  lastLoginAt: string | null
}

/** A failed job, without its payload (FailedJobResource). */
export interface FailedJob {
  uuid: string
  queue: string
  connection: string
  /** Class name of the job. */
  job: string
  /** First line of the exception, masked and truncated. */
  exception: string
  /** ISO 8601 date. */
  failedAt: string
}

export interface FailedJobs {
  total: number
  latest: FailedJob[]
}

export type FailedJobAction = 'retry' | 'destroy'

export type ErrorLogLevel = 'error' | 'critical' | 'alert' | 'emergency'

/** An error of the Laravel log, message only (ErrorLogEntryResource). */
export interface ErrorLogEntry {
  level: ErrorLogLevel
  message: string
  /** ISO 8601 date. */
  loggedAt: string
}

export interface GoogleBooksStatusCount {
  status: string
  label: string
  count: number
}

/** Calls to Google Books over a period (GoogleBooksPeriodResource). */
export interface GoogleBooksPeriod {
  key: 'day' | 'week'
  label: string
  total: number
  averageDurationMs: number | null
  statuses: GoogleBooksStatusCount[]
}

/** A failed call to Google Books (GoogleBooksFailureResource). */
export interface GoogleBooksFailure {
  id: number
  /** French label of the type of call. */
  type: string
  query: string
  status: string
  statusLabel: string
  httpStatus: number | null
  member: string | null
  /** ISO 8601 date. */
  createdAt: string | null
}

export interface GoogleBooksActivity {
  periods: GoogleBooksPeriod[]
  failures: GoogleBooksFailure[]
}
