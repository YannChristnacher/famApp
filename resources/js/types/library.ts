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
