import { computed, onScopeDispose, ref, watch } from 'vue'
import type { Ref } from 'vue'
import { route } from 'ziggy-js'
import type { SearchGroup, SearchResponse } from '@/types/search'

/** Pause in the typing before the server is asked. */
export const SEARCH_DEBOUNCE_MS = 250
/** Same bounds as SearchRequest on the server (characters, once trimmed). */
export const SEARCH_MIN_LENGTH = 2
export const SEARCH_MAX_LENGTH = 100

const NETWORK_ERROR = 'La recherche n’a pas pu aboutir. Vérifiez votre connexion.'
const UNKNOWN_ERROR = 'La recherche n’a pas pu aboutir. Réessayez dans un instant.'
const SESSION_EXPIRED = 'Votre session a expiré. Rechargez la page pour vous reconnecter.'
const TOO_LONG = `La recherche est limitée à ${SEARCH_MAX_LENGTH} caractères.`

function errorMessage(status: number, body: unknown): string {
  if (status === 401 || status === 419) {
    return SESSION_EXPIRED
  }

  const message = (body as { message?: unknown } | null)?.message

  return status === 429 && typeof message === 'string' ? message : UNKNOWN_ERROR
}

function isSearchResponse(body: unknown): body is SearchResponse {
  return typeof body === 'object' && body !== null && Array.isArray((body as SearchResponse).groups)
}

async function jsonBody(response: Response): Promise<unknown> {
  try {
    return await response.json()
  } catch {
    return null
  }
}

/** Length in characters, as the server counts it (not in UTF-16 units). */
function characterCount(text: string): number {
  return Array.from(text).length
}

/**
 * Global search of the header: asks the server for the typed text once the
 * typing pauses, aborts the request made obsolete by a newer text and
 * ignores its late answer.
 */
export function useGlobalSearch(term: Ref<string>) {
  const groups = ref<SearchGroup[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const query = computed(() => term.value.trim())
  const tooShort = computed(() => characterCount(query.value) < SEARCH_MIN_LENGTH)

  let timer: ReturnType<typeof setTimeout> | undefined
  let pending: AbortController | null = null

  function cancel(): void {
    clearTimeout(timer)
    pending?.abort()
    pending = null
    loading.value = false
  }

  async function send(text: string): Promise<void> {
    const request = new AbortController()
    pending = request

    try {
      const response = await fetch(route('search', { q: text }, false), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: request.signal,
      })
      const body = await jsonBody(response)
      if (pending !== request) {
        return
      }

      if (response.ok && isSearchResponse(body)) {
        groups.value = body.groups
      } else {
        groups.value = []
        error.value = response.ok ? UNKNOWN_ERROR : errorMessage(response.status, body)
      }
    } catch {
      if (pending !== request) {
        return
      }
      groups.value = []
      error.value = NETWORK_ERROR
    } finally {
      if (pending === request) {
        pending = null
        loading.value = false
      }
    }
  }

  watch(query, (text) => {
    cancel()
    error.value = null

    if (tooShort.value || characterCount(text) > SEARCH_MAX_LENGTH) {
      groups.value = []
      error.value = tooShort.value ? null : TOO_LONG

      return
    }

    loading.value = true
    timer = setTimeout(() => void send(text), SEARCH_DEBOUNCE_MS)
  })

  onScopeDispose(cancel)

  return { groups, loading, error, tooShort, query }
}
