<script setup lang="ts">
import { computed, ref } from 'vue'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BookCover from '@/components/Library/BookCover.vue'
import BookDeleteModal from '@/components/Library/BookDeleteModal.vue'
import type { BookDetails } from '@/types/library'
import type { Member } from '@/types/family'

const props = defineProps<{
  book: BookDetails
  /** The member the book belongs to. */
  owner: Member
  /** True for the owner only: the page is read-only for the rest of the family. */
  editable: boolean
}>()

const breadcrumb = computed(() => (props.editable
  ? [
      { label: 'Bibliothèque' },
      { label: 'Mes livres', to: route('library.books.index', undefined, false) },
      { label: props.book.title },
    ]
  : [
      { label: 'La famille', to: route('family.index', undefined, false) },
      { label: props.owner.name, to: route('family.members.show', { member: props.owner.id }, false) },
      { label: props.book.title },
    ]))

/** The list of the books on a bookshelf: « Mes livres » for the owner, the page of the member otherwise. */
function bookshelfUrl(bookshelf: number): string {
  return props.editable
    ? route('library.books.index', { bookshelf }, false)
    : route('family.members.show', { member: props.owner.id, bookshelf }, false)
}

const deleteOpen = ref(false)
</script>

<template>
  <DashboardLayout
    :title="book.title"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-4xl">
      <div class="flex flex-col gap-6 sm:flex-row">
        <BookCover
          :url="book.cover_url"
          :title="book.title"
          size="page"
          class="mx-auto shrink-0 sm:mx-0"
        />

        <div class="min-w-0 flex-1 space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-highlighted">
              {{ book.title }}
            </h2>
            <p class="text-lg text-muted">
              {{ book.author }}
            </p>
          </div>

          <dl class="space-y-3 text-sm">
            <div>
              <dt class="font-medium text-highlighted">
                ISBN
              </dt>
              <dd
                class="text-muted"
                data-testid="isbn"
              >
                {{ book.isbn ?? 'Non renseigné' }}
              </dd>
            </div>
            <div>
              <dt class="font-medium text-highlighted">
                Étagères
              </dt>
              <dd class="mt-1 flex flex-wrap gap-2">
                <UButton
                  v-for="bookshelf in book.bookshelves"
                  :key="bookshelf.id"
                  :to="bookshelfUrl(bookshelf.id)"
                  :label="bookshelf.name"
                  icon="i-lucide-library"
                  color="neutral"
                  variant="subtle"
                  size="xs"
                  data-testid="book-bookshelf"
                />
                <span
                  v-if="book.bookshelves.length === 0"
                  class="text-muted"
                >Rangé sur aucune étagère</span>
              </dd>
            </div>
            <div v-if="book.comment">
              <dt class="font-medium text-highlighted">
                Commentaire
              </dt>
              <dd
                class="mt-1 whitespace-pre-line break-words text-muted"
                data-testid="book-comment"
                v-text="book.comment"
              />
            </div>
          </dl>

          <p
            v-if="!editable"
            class="text-sm text-muted"
            data-testid="owner"
          >
            Dans la bibliothèque de {{ owner.name }}
          </p>

          <div
            v-if="editable"
            class="flex flex-wrap gap-2 pt-2"
          >
            <UButton
              :to="route('library.books.edit', { book: book.id }, false)"
              icon="i-lucide-pencil"
            >
              Modifier
            </UButton>
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="outline"
              @click="deleteOpen = true"
            >
              Supprimer
            </UButton>
          </div>
        </div>
      </div>
    </UCard>

    <BookDeleteModal
      v-if="editable"
      v-model:open="deleteOpen"
      :book="book"
    />
  </DashboardLayout>
</template>
