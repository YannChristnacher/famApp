import { describe, expect, it } from 'vitest'
import { bookIsbnFromBarcode } from './bookBarcode'

describe('bookIsbnFromBarcode', () => {
  it.each([
    '9782070368228',
    '9780306406157',
    '9791032305690',
    '9790260000438',
  ])('accepts the book EAN-13 %s', (code) => {
    expect(bookIsbnFromBarcode(code)).toBe(code)
  })

  it('trims surrounding spaces', () => {
    expect(bookIsbnFromBarcode(' 9782070368228\n')).toBe('9782070368228')
  })

  it.each([
    ['a valid EAN-13 of another product', '3017620422003'],
    ['a valid EAN-13 with prefix 977 (periodicals)', '9771234567003'],
    ['a wrong check digit', '9782070368229'],
    ['a 12-digit code', '978207036822'],
    ['a 14-digit code', '97820703682280'],
    ['an ISBN-10', '2070368221'],
    ['letters', '978207036822X'],
    ['hyphens', '978-2-07-036822-8'],
    ['an empty string', ''],
  ])('refuses %s', (_, code) => {
    expect(bookIsbnFromBarcode(code)).toBeNull()
  })
})
