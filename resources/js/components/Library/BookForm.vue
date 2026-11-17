<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import BookCover from '@/components/Library/BookCover.vue'
import type { Book, Bookshelf } from '@/types/library'

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
}

const form = useForm<BookFormData>({
  title: props.book?.title ?? '',
  author: props.book?.author ?? '',
  isbn: props.book?.isbn ?? '',
  bookshelf_ids: props.book?.bookshelves.map((bookshelf) => bookshelf.id) ?? [],
  cover: null,
  remove_cover: false,
})

/** Errors of the list itself or of one of its items (bookshelf_ids.0…). */
const bookshelfError = computed(() => Object.entries(form.errors as Record<string, string | undefined>)
  .find(([field]) => field === 'bookshelf_ids' || field.startsWith('bookshelf_ids.'))?.[1])

const showsCurrentCover = computed(() => props.book?.cover_url != null && form.cover === null && !form.remove_cover)

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
        help="Facultatif : 10 ou 13 chiffres, tirets et espaces acceptés."
        :error="form.errors.isbn"
      >
        <UInput
          v-model="form.isbn"
          name="isbn"
          inputmode="numeric"
          autocomplete="off"
          class="w-full"
        />
      </UFormField>

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
          v-if="showsCurrentCover"
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
