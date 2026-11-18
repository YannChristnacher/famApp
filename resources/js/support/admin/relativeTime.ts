const relativeFormat = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' })
const fullFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' })

const units: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 365 * 24 * 3600],
  ['month', 30 * 24 * 3600],
  ['week', 7 * 24 * 3600],
  ['day', 24 * 3600],
  ['hour', 3600],
  ['minute', 60],
]

/**
 * Time elapsed since an ISO 8601 date, in French: « à l'instant »,
 * « il y a 5 minutes », « hier », « il y a 3 semaines »…
 */
export function relativeTime(isoDate: string, now: Date = new Date()): string {
  const elapsedSeconds = Math.round((now.getTime() - new Date(isoDate).getTime()) / 1000)

  if (Math.abs(elapsedSeconds) < 45) {
    return 'à l\'instant'
  }

  for (const [unit, seconds] of units) {
    if (Math.abs(elapsedSeconds) >= seconds) {
      return relativeFormat.format(-Math.round(elapsedSeconds / seconds), unit)
    }
  }

  return relativeFormat.format(-Math.max(1, Math.round(elapsedSeconds / 60)), 'minute')
}

/** Complete French date and time, e.g. « 18 novembre 2026 à 09:05 ». */
export function fullDate(isoDate: string): string {
  return fullFormat.format(new Date(isoDate))
}
