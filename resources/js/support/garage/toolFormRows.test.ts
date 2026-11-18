import { afterEach, describe, expect, it, vi } from 'vitest'
import { isProxy, reactive } from 'vue'
import { accessoryRow, moved, newPhotoRow, normalizedBrand } from './toolFormRows'

describe('support/garage/toolFormRows', () => {
  afterEach(() => {
    Reflect.deleteProperty(URL, 'createObjectURL')
  })

  it('moves an item up or down, and nothing at the ends', () => {
    expect(moved(['a', 'b', 'c'], 1, -1)).toEqual(['b', 'a', 'c'])
    expect(moved(['a', 'b', 'c'], 1, 1)).toEqual(['a', 'c', 'b'])
    expect(moved(['a', 'b', 'c'], 0, -1)).toEqual(['a', 'b', 'c'])
    expect(moved(['a', 'b', 'c'], 2, 1)).toEqual(['a', 'b', 'c'])
  })

  it('normalizes a brand as the server does: trimmed, spaces reduced, lower case, accents kept', () => {
    expect(normalizedBrand('  Black   &  DECKER ')).toBe('black & decker')
    expect(normalizedBrand('Leröy')).not.toBe(normalizedBrand('Leroy'))
  })

  it('gives each row its own key', () => {
    expect(accessoryRow('Lame').key).not.toBe(accessoryRow('Lame').key)
  })

  it('previews a new photo and keeps the file raw, even when it comes reactive', () => {
    URL.createObjectURL = vi.fn(() => 'blob:preview')
    const file = new File(['x'], 'x.jpg', { type: 'image/jpeg' })
    const proxied = reactive({ files: [file] }).files[0]!

    const row = newPhotoRow(proxied)

    expect(row.url).toBe('blob:preview')
    expect('file' in row && row.file).toBe(file)
    // Stored in a reactive form, the file stays raw (markRaw).
    const stored = reactive({ row }).row
    expect('file' in stored && isProxy(stored.file)).toBe(false)
    expect(URL.createObjectURL).toHaveBeenCalledWith(file)
  })
})
