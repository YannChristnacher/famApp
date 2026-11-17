<script setup lang="ts">
import { computed, ref } from 'vue'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BookCover from '@/components/Library/BookCover.vue'
import BookDeleteModal from '@/components/Library/BookDeleteModal.vue'
import type { Book } from '@/types/library'

const props = defineProps<{
  book: Book
}>()

const breadcrumb = computed(() => [
  { label: 'Bibliothèque' },
  { label: 'Mes livres', to: route('library.books.index', undefined, false) },
  { label: props.book.title },
])

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
                  :to="route('library.books.index', { bookshelf: bookshelf.id }, false)"
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
          </dl>

          <div class="flex flex-wrap gap-2 pt-2">
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
      v-model:open="deleteOpen"
      :book="book"
    />
  </DashboardLayout>
</template>
