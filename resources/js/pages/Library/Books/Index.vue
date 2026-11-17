<script setup lang="ts">
import { computed } from 'vue'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BookCard from '@/components/Library/BookCard.vue'
import type { Book, Bookshelf } from '@/types/library'

const props = defineProps<{
  /** The books of the signed-in member (on the filtered bookshelf), by title. */
  books: Book[]
  /** Their bookshelves, by position, for the filter. */
  bookshelves: Bookshelf[]
  /** Id of the bookshelf the list is filtered on, null for every book. */
  filter: number | null
}>()

const breadcrumb = [
  { label: 'Bibliothèque' },
  { label: 'Mes livres' },
]

const filtered = computed(() => props.bookshelves.find((bookshelf) => bookshelf.id === props.filter) ?? null)

const filters = computed(() => [
  { key: 'all', label: 'Tous mes livres', to: route('library.books.index', undefined, false), active: props.filter === null },
  ...props.bookshelves.map((bookshelf) => ({
    key: `bookshelf-${bookshelf.id}`,
    label: bookshelf.name,
    to: route('library.books.index', { bookshelf: bookshelf.id }, false),
    active: bookshelf.id === props.filter,
  })),
])
</script>

<template>
  <DashboardLayout
    title="Mes livres"
    :breadcrumb="breadcrumb"
  >
    <div class="mx-auto w-full max-w-6xl space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <nav
          v-if="bookshelves.length > 0"
          aria-label="Filtrer par étagère"
          class="flex flex-wrap gap-2"
        >
          <UButton
            v-for="item in filters"
            :key="item.key"
            :to="item.to"
            :label="item.label"
            :color="item.active ? 'primary' : 'neutral'"
            :variant="item.active ? 'solid' : 'outline'"
            :aria-current="item.active ? 'page' : undefined"
            size="sm"
            data-testid="bookshelf-filter"
          />
        </nav>
        <div class="ms-auto flex gap-2">
          <UButton
            :to="route('library.bookshelves.index', undefined, false)"
            icon="i-lucide-library"
            color="neutral"
            variant="ghost"
          >
            Mes étagères
          </UButton>
          <UButton
            :to="route('library.books.create', undefined, false)"
            icon="i-lucide-plus"
          >
            Ajouter un livre
          </UButton>
        </div>
      </div>

      <UEmpty
        v-if="books.length === 0 && filtered === null"
        icon="i-lucide-book-open"
        title="Vous n'avez encore aucun livre."
        description="Ajoutez votre premier livre pour le montrer à la famille."
        :actions="[{ label: 'Ajouter un livre', icon: 'i-lucide-plus', to: route('library.books.create', undefined, false) }]"
        variant="naked"
      />
      <UEmpty
        v-else-if="books.length === 0"
        icon="i-lucide-book-open"
        :title="`Aucun livre sur l'étagère « ${filtered?.name} ».`"
        description="Rangez un livre sur cette étagère depuis sa fiche."
        variant="naked"
      />

      <ul
        v-else
        class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
      >
        <li
          v-for="book in books"
          :key="book.id"
        >
          <BookCard :book="book" />
        </li>
      </ul>
    </div>
  </DashboardLayout>
</template>
