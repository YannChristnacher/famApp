import { describe, expect, it } from 'vitest'
import { countLabel, inventoryLabel } from './counts'

describe('countLabel', () => {
  it.each([
    [0, '0 livre'],
    [1, '1 livre'],
    [2, '2 livres'],
    [12, '12 livres'],
  ])('writes %i with the French plural rule', (count, expected) => {
    expect(countLabel(count, 'livre', 'livres')).toBe(expected)
  })
})

describe('inventoryLabel', () => {
  it('joins the numbers of books and tools', () => {
    expect(inventoryLabel(3, 1)).toBe('3 livres · 1 outil')
    expect(inventoryLabel(0, 0)).toBe('0 livre · 0 outil')
  })
})
