import { describe, expect, it } from 'vitest'
import { fullDate, relativeTime } from './relativeTime'

const now = new Date('2026-11-18T12:00:00Z')

describe('relativeTime', () => {
  it.each([
    ['2026-11-18T11:59:50Z', 'à l\'instant'],
    ['2026-11-18T11:55:00Z', 'il y a 5 minutes'],
    ['2026-11-18T11:00:00Z', 'il y a 1 heure'],
    ['2026-11-18T09:00:00Z', 'il y a 3 heures'],
    ['2026-11-17T12:00:00Z', 'hier'],
    ['2026-11-15T12:00:00Z', 'il y a 3 jours'],
    ['2026-11-04T12:00:00Z', 'il y a 2 semaines'],
    ['2026-08-18T12:00:00Z', 'il y a 3 mois'],
    ['2024-11-18T12:00:00Z', 'il y a 2 ans'],
  ])('describes %s as « %s »', (isoDate, expected) => {
    expect(relativeTime(isoDate, now).replace(/ | /g, ' ')).toBe(expected)
  })

  it('treats a slightly future date (clock drift) as now', () => {
    expect(relativeTime('2026-11-18T12:00:20Z', now)).toBe('à l\'instant')
  })
})

describe('fullDate', () => {
  it('writes the complete French date', () => {
    expect(fullDate('2026-11-18T09:05:00Z')).toContain('novembre 2026')
  })
})
