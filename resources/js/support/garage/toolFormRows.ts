import { markRaw, toRaw } from 'vue'

let lastKey = 0

/** Key of a row of the tool form, stable while the member reorders rows. */
export function nextRowKey(): number {
  lastKey += 1

  return lastKey
}

/** An accessory line of the tool form. */
export interface AccessoryRow {
  key: number
  name: string
}

/** A photo of the tool form: a photo of the tool to keep, or a new file. */
export type PhotoRow =
  | { key: number, id: number, url: string }
  | { key: number, file: File, url: string }

export function accessoryRow(name = ''): AccessoryRow {
  return { key: nextRowKey(), name }
}

/**
 * A new photo, previewed with an object URL (to revoke once removed). The
 * file is kept raw: native APIs refuse Vue's reactive proxies.
 */
export function newPhotoRow(file: File): PhotoRow {
  const raw = markRaw(toRaw(file))

  return { key: nextRowKey(), file: raw, url: URL.createObjectURL(raw) }
}

/** Moves the item at index by offset (-1 up, 1 down); nothing at the ends. */
export function moved<T>(items: T[], index: number, offset: -1 | 1): T[] {
  const target = index + offset
  if (target < 0 || target >= items.length) {
    return items
  }

  const copy = [...items]
  ;[copy[index], copy[target]] = [copy[target]!, copy[index]!]

  return copy
}

/** Normalized form of a brand, as the server compares them (case, spaces). */
export function normalizedBrand(name: string): string {
  return name.trim().replace(/[ \t\n\r\f\v]+/g, ' ').toLowerCase()
}
