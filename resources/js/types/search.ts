/**
 * One result of the global search (SearchResult on the server): the URL is
 * relative and computed by the module that found it.
 */
export interface SearchResult {
  type: string
  id: number
  label: string
  description: string | null
  url: string
  image: string | null
}

/**
 * The results of one module, with the French title and icon of its group.
 */
export interface SearchGroup {
  key: string
  label: string
  icon: string
  results: SearchResult[]
}

export interface SearchResponse {
  groups: SearchGroup[]
}
