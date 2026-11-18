/**
 * « 0 livre », « 1 livre », « 3 livres »: in French, 0 and 1 take the singular.
 */
export function countLabel(count: number, singular: string, plural: string): string {
  return `${count} ${count <= 1 ? singular : plural}`
}

/** « 3 livres · 1 outil », for the list of the family. */
export function inventoryLabel(books: number, tools: number): string {
  return `${countLabel(books, 'livre', 'livres')} · ${countLabel(tools, 'outil', 'outils')}`
}
