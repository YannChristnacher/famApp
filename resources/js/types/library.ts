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
