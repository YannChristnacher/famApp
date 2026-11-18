<script setup lang="ts">
import { computed } from 'vue'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BookForm from '@/components/Library/BookForm.vue'
import type { BookDetails, Bookshelf } from '@/types/library'

const props = defineProps<{
  book: BookDetails
  /** The bookshelves of the signed-in member, by position. */
  bookshelves: Bookshelf[]
}>()

const breadcrumb = computed(() => [
  { label: 'Bibliothèque' },
  { label: 'Mes livres', to: route('library.books.index', undefined, false) },
  { label: props.book.title, to: route('library.books.show', { book: props.book.id }, false) },
  { label: 'Modifier' },
])
</script>

<template>
  <DashboardLayout
    :title="`Modifier « ${book.title} »`"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-4xl">
      <BookForm
        :key="book.id"
        :book="book"
        :bookshelves="bookshelves"
      />
    </UCard>
  </DashboardLayout>
</template>
