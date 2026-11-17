import { ref } from 'vue'
import { route } from 'ziggy-js'
import type { GoogleBooksLookup } from '@/types/library'

const NETWORK_ERROR = 'La recherche n’a pas pu aboutir. Vérifiez votre connexion.'
const UNKNOWN_ERROR = 'La recherche n’a pas pu aboutir. Réessayez ou saisissez le livre à la main.'
const SESSION_EXPIRED = 'Votre session a expiré. Rechargez la page pour vous reconnecter.'

interface ErrorBody {
  message?: unknown
  errors?: Record<string, unknown>
}

/**
 * French message of a failed lookup: the server's own message (not found,
 * Google unavailable, too many searches, invalid ISBN), or a generic one.
 */
function errorMessage(status: number, body: ErrorBody | null): string {
  if (status === 401 || status === 419) {
    return SESSION_EXPIRED
  }

  const isbnErrors = body?.errors?.isbn
  if (Array.isArray(isbnErrors) && typeof isbnErrors[0] === 'string') {
    return isbnErrors[0]
  }

  return typeof body?.message === 'string' && status !== 500 ? body.message : UNKNOWN_ERROR
}

function isLookup(body: unknown): body is GoogleBooksLookup {
  return typeof body === 'object' && body !== null
    && typeof (body as GoogleBooksLookup).volume_id === 'string'
    && typeof (body as GoogleBooksLookup).title === 'string'
}

async function jsonBody(response: Response): Promise<unknown> {
  try {
    return await response.json()
  } catch {
    return null
  }
}

/**
 * Searches an ISBN on Google Books through the server (the API key and the
 * raw data of Google never reach the browser).
 */
export function useGoogleBooksLookup() {
  const searching = ref(false)
  const error = ref<string | null>(null)

  async function lookup(isbn: string): Promise<GoogleBooksLookup | null> {
    searching.value = true
    error.value = null

    try {
      const response = await fetch(route('library.google-books.lookup', { isbn }, false), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
      const body = await jsonBody(response)

      if (response.ok && isLookup(body)) {
        return body
      }

      error.value = response.ok ? UNKNOWN_ERROR : errorMessage(response.status, body as ErrorBody | null)

      return null
    } catch {
      error.value = NETWORK_ERROR

      return null
    } finally {
      searching.value = false
    }
  }

  return { lookup, searching, error }
}
