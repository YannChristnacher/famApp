/**
 * A shelf of the garage of the signed-in member (ToolShelfResource).
 */
export interface ToolShelf {
  id: number
  name: string
  /** 1 for the first shelf of the garage. */
  position: number
}

export type ToolShelfMoveDirection = 'up' | 'down'
