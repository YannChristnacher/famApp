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

/**
 * A brand of tools, shared by the family (BrandResource).
 */
export interface Brand {
  id: number
  name: string
}

/**
 * A shelf a tool is on (ToolResource.shelves).
 */
export interface ToolShelfSummary {
  id: number
  name: string
}

/**
 * A tool of the signed-in member, as listed (ToolResource).
 */
export interface Tool {
  id: number
  name: string
  brand: Brand | null
  /** Public URL of the first photo, null without photo. */
  thumbnail_url: string | null
  /** In the order of the garage. */
  shelves: ToolShelfSummary[]
}

export interface ToolPhoto {
  id: number
  /** Public URL of the resized photo. */
  url: string
}

export interface ToolAccessory {
  id: number
  name: string
}

/**
 * A tool with its photos and accessories, in order (ToolDetailsResource).
 */
export interface ToolDetails extends Tool {
  photos: ToolPhoto[]
  accessories: ToolAccessory[]
  /** Free text of the owner (plain text, line breaks kept), null without comment. */
  comment: string | null
}

/** Filters of the list of tools (ids, null when not filtered). */
export interface ToolFilters {
  shelf: number | null
  brand: number | null
}
