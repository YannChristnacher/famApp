/**
 * A bookshelf of the signed-in member (BookshelfResource).
 */
export interface Bookshelf {
  id: number
  name: string
  /** 1 for the first bookshelf of the library. */
  position: number
}

export type BookshelfMoveDirection = 'up' | 'down'

/**
 * A bookshelf a book is on (BookResource.bookshelves).
 */
export interface BookshelfSummary {
  id: number
  name: string
}

/**
 * A book of the signed-in member (BookResource).
 */
export interface Book {
  id: number
  title: string
  author: string
  /** Normalized ISBN-10 or ISBN-13, null when unknown. */
  isbn: string | null
  /** Public URL of the resized cover, null without cover. */
  cover_url: string | null
  /** In the order of the library. */
  bookshelves: BookshelfSummary[]
}

/**
 * A book with its comment, for its page and its form (BookDetailsResource).
 */
export interface BookDetails extends Book {
  /** Free text of the owner (plain text, line breaks kept), null without comment. */
  comment: string | null
}

/**
 * Prefill of the book form found on Google Books (GoogleBooksLookupResource).
 */
export interface GoogleBooksLookup {
  /** Identifier of the Google volume, sent back when the book is saved. */
  volume_id: string
  title: string
  /** The authors joined by ", ", empty when Google lists none. */
  author: string
  /** https URL of the Google cover, null without cover. */
  cover_url: string | null
}
