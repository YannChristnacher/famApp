<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from '@nuxt/ui/composables'
import { route } from 'ziggy-js'
import BookCover from '@/components/Library/BookCover.vue'
import IsbnScannerModal from '@/components/Library/IsbnScannerModal.vue'
import { useGoogleBooksLookup } from '@/composables/useGoogleBooksLookup'
import type { Book, Bookshelf, GoogleBooksLookup } from '@/types/library'

const props = defineProps<{
  /** The edited book, null to create one. */
  book: Book | null
  /** The bookshelves of the member, by position. */
  bookshelves: Bookshelf[]
}>()

interface BookFormData {
  title: string
  author: string
  isbn: string
  bookshelf_ids: number[]
  cover: File | null
  remove_cover: boolean
  /** Volume found on Google Books: the server fetches its data again. */
  google_volume_id: string | null
  /** Asks the server to download the Google cover (an uploaded file wins). */
  use_google_cover: boolean
}

const form = useForm<BookFormData>({
  title: props.book?.title ?? '',
  author: props.book?.author ?? '',
  isbn: props.book?.isbn ?? '',
  bookshelf_ids: props.book?.bookshelves.map((bookshelf) => bookshelf.id) ?? [],
  cover: null,
  remove_cover: false,
  google_volume_id: null,
  use_google_cover: false,
})

const { lookup, searching, error: lookupError } = useGoogleBooksLookup()
/** The volume linked to the form, until the notice is closed or a new search. */
const googleBook = ref<GoogleBooksLookup | null>(null)
/** A result that differs from what the member already typed: waits for their choice. */
const proposal = ref<GoogleBooksLookup | null>(null)

const canSearch = computed(() => form.isbn.trim() !== '' && !searching.value)

const showsGoogleCover = computed(() => googleBook.value?.cover_url != null && form.use_google_cover && form.cover === null)

function differsFromTyped(typed: string, found: string): boolean {
  return typed.trim() !== '' && found !== '' && typed.trim() !== found
}

/**
 * Empty fields are filled at once; typed ones are never overwritten without
 * the member's consent ("Remplacer" or "Garder ma saisie").
 */
async function searchGoogleBooks(): Promise<void> {
  if (!canSearch.value) {
    return
  }

  unlinkGoogleBook()
  const found = await lookup(form.isbn.trim())
  if (found === null) {
    return
  }

  googleBook.value = found
  form.google_volume_id = found.volume_id
  form.use_google_cover = found.cover_url !== null
  form.clearErrors('isbn', 'google_volume_id')

  if (differsFromTyped(form.title, found.title) || differsFromTyped(form.author, found.author)) {
    proposal.value = found

    return
  }

  form.title = form.title.trim() === '' ? found.title : form.title
  form.author = form.author.trim() === '' ? found.author : form.author
}

const scannerOpen = ref(false)
const toast = useToast()

/** A scanned barcode fills the ISBN and searches it (one search, same limiter). */
async function applyScannedIsbn(isbn: string): Promise<void> {
  form.isbn = isbn
  form.clearErrors('isbn')
  toast.add({ title: 'Code-barres lu', description: `ISBN ${isbn}`, color: 'success', icon: 'i-lucide-scan-barcode' })
  await searchGoogleBooks()
}

function replaceTypedFields(): void {
  if (proposal.value === null) {
    return
  }

  form.title = proposal.value.title
  form.author = proposal.value.author === '' ? form.author : proposal.value.author
  proposal.value = null
}

function keepTypedFields(): void {
  proposal.value = null
}

function unlinkGoogleBook(): void {
  googleBook.value = null
  proposal.value = null
  form.google_volume_id = null
  form.use_google_cover = false
}

/** Errors of the list itself or of one of its items (bookshelf_ids.0…). */
const bookshelfError = computed(() => Object.entries(form.errors as Record<string, string | undefined>)
  .find(([field]) => field === 'bookshelf_ids' || field.startsWith('bookshelf_ids.'))?.[1])

const showsCurrentCover = computed(() => props.book?.cover_url != null && form.cover === null && !form.remove_cover && !showsGoogleCover.value)

const cancelUrl = computed(() => props.book === null
  ? route('library.books.index', undefined, false)
  : route('library.books.show', { book: props.book.id }, false))

function submit(): void {
  if (props.book === null) {
    form.post(route('library.books.store', undefined, false), { preserveScroll: true })

    return
  }

  // A file cannot be sent with PATCH (PHP reads multipart bodies on POST only).
  form
    .transform((data) => ({ ...data, _method: 'patch' }))
    .post(route('library.books.update', { book: props.book.id }, false), { preserveScroll: true })
}
</script>

<template>
  <UForm
    :state="form"
    class="grid gap-6 md:grid-cols-[1fr_16rem]"
    @submit="submit"
  >
    <div class="space-y-4">
      <UFormField
        label="Titre"
        name="title"
        required
        :error="form.errors.title"
      >
        <UInput
          v-model="form.title"
          name="title"
          autocomplete="off"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Auteur"
        name="author"
        required
        :error="form.errors.author"
      >
        <UInput
          v-model="form.author"
          name="author"
          autocomplete="off"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="ISBN"
        name="isbn"
        help="Facultatif : 10 ou 13 chiffres, tirets et espaces acceptés. « Rechercher » préremplit le titre, l’auteur et la couverture depuis Google Books."
        :error="form.errors.isbn ?? lookupError ?? form.errors.google_volume_id ?? undefined"
      >
        <div class="flex gap-2">
          <UInput
            v-model="form.isbn"
            name="isbn"
            inputmode="numeric"
            autocomplete="off"
            class="w-full"
          />
          <UButton
            icon="i-lucide-scan-barcode"
            color="neutral"
            variant="outline"
            aria-label="Scanner le code-barres avec la caméra"
            data-testid="isbn-scan"
            @click="scannerOpen = true"
          >
            Scanner
          </UButton>
          <UButton
            icon="i-lucide-search"
            color="neutral"
            variant="outline"
            :loading="searching"
            :aria-busy="searching"
            :disabled="!canSearch"
            data-testid="google-books-search"
            @click="searchGoogleBooks"
          >
            Rechercher
          </UButton>
        </div>
      </UFormField>

      <IsbnScannerModal
        v-model:open="scannerOpen"
        @detected="applyScannedIsbn"
      />

      <UAlert
        v-if="proposal"
        color="warning"
        variant="subtle"
        icon="i-lucide-replace"
        title="Remplacer votre saisie ?"
        :description="`Google Books propose « ${proposal.title} »${proposal.author ? ` de ${proposal.author}` : ''}.`"
        :actions="[
          { label: 'Remplacer', color: 'warning', onClick: replaceTypedFields },
          { label: 'Garder ma saisie', color: 'neutral', variant: 'outline', onClick: keepTypedFields },
        ]"
        data-testid="google-books-conflict"
      />

      <UAlert
        v-if="googleBook"
        color="info"
        variant="subtle"
        icon="i-lucide-book-check"
        title="Trouvé sur Google Books"
        :description="`« ${googleBook.title} »${googleBook.author ? ` de ${googleBook.author}` : ''}. Vérifiez les informations avant d’enregistrer ; fermez cet encart pour ne garder que votre saisie.`"
        :close="{ 'aria-label': 'Ne pas lier ce livre à Google Books' }"
        data-testid="google-books-result"
        @update:open="unlinkGoogleBook"
      />

      <UFormField
        label="Étagères"
        name="bookshelf_ids"
        :help="bookshelves.length === 0 ? 'Vous n’avez pas encore d’étagère : le livre restera hors étagère.' : undefined"
        :error="bookshelfError"
      >
        <USelectMenu
          v-model="form.bookshelf_ids"
          :items="bookshelves"
          value-key="id"
          label-key="name"
          multiple
          name="bookshelf_ids"
          placeholder="Choisir une ou plusieurs étagères"
          :search-input="{ placeholder: 'Filtrer…' }"
          :disabled="bookshelves.length === 0"
          data-testid="bookshelf-select"
          class="w-full"
        />
      </UFormField>
    </div>

    <div class="space-y-3">
      <UFormField
        label="Couverture"
        name="cover"
        :error="form.errors.cover"
      >
        <div
          v-if="showsGoogleCover"
          class="space-y-2"
          data-testid="google-cover"
        >
          <BookCover
            :url="googleBook?.cover_url ?? null"
            :title="form.title"
          />
          <p class="text-xs text-muted">
            Couverture Google Books, enregistrée avec le livre.
          </p>
          <UButton
            icon="i-lucide-image-off"
            color="neutral"
            variant="outline"
            size="sm"
            block
            @click="form.use_google_cover = false"
          >
            Ne pas utiliser cette couverture
          </UButton>
        </div>
        <div
          v-else-if="showsCurrentCover"
          class="space-y-2"
          data-testid="current-cover"
        >
          <BookCover
            :url="book?.cover_url ?? null"
            :title="book?.title ?? ''"
          />
          <UButton
            icon="i-lucide-image-off"
            color="neutral"
            variant="outline"
            size="sm"
            block
            @click="form.remove_cover = true"
          >
            Retirer la couverture
          </UButton>
        </div>
        <UAlert
          v-else-if="form.remove_cover && form.cover === null"
          color="neutral"
          variant="subtle"
          title="La couverture sera retirée."
          :actions="[{ label: 'Annuler', color: 'neutral', variant: 'outline', onClick: () => { form.remove_cover = false } }]"
          class="mb-2"
        />
        <UButton
          v-if="googleBook?.cover_url && !form.use_google_cover && form.cover === null"
          icon="i-lucide-image"
          color="neutral"
          variant="outline"
          size="sm"
          block
          class="mb-2"
          @click="form.use_google_cover = true"
        >
          Utiliser la couverture Google
        </UButton>
        <UFileUpload
          v-model="form.cover"
          name="cover"
          accept="image/jpeg,image/png,image/webp"
          :label="book?.cover_url ? 'Remplacer la couverture' : 'Ajouter une couverture'"
          description="JPEG, PNG ou WebP, 10 Mo maximum."
          class="mt-2 min-h-48 w-full"
        />
      </UFormField>
    </div>

    <div class="flex justify-end gap-2 md:col-span-2">
      <UButton
        :to="cancelUrl"
        color="neutral"
        variant="ghost"
      >
        Annuler
      </UButton>
      <UButton
        type="submit"
        icon="i-lucide-check"
        :loading="form.processing"
      >
        {{ book === null ? 'Ajouter le livre' : 'Enregistrer' }}
      </UButton>
    </div>
  </UForm>
</template>
