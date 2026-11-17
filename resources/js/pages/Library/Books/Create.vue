<script setup lang="ts">
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BookForm from '@/components/Library/BookForm.vue'
import type { Bookshelf } from '@/types/library'

defineProps<{
  /** The bookshelves of the signed-in member, by position. */
  bookshelves: Bookshelf[]
}>()

const breadcrumb = [
  { label: 'Bibliothèque' },
  { label: 'Mes livres', to: route('library.books.index', undefined, false) },
  { label: 'Ajouter un livre' },
]
</script>

<template>
  <DashboardLayout
    title="Ajouter un livre"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-4xl">
      <BookForm
        :book="null"
        :bookshelves="bookshelves"
      />
    </UCard>
  </DashboardLayout>
</template>
