/**
 * A member as shown to the rest of the family (MemberResource): never their email.
 */
export interface Member {
  id: number
  name: string
}

/**
 * A member in the list of the family (FamilyMemberResource).
 */
export interface FamilyMember extends Member {
  books_count: number
  tools_count: number
  /** True for the signed-in member. */
  own: boolean
}

export type MemberTab = 'library' | 'garage'

/** Shelf filters of the page of a member (ids, null when not filtered). */
export interface MemberFilters {
  bookshelf: number | null
  shelf: number | null
}
