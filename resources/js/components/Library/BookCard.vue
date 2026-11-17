<script setup lang="ts">
import { route } from 'ziggy-js'
import BookCover from '@/components/Library/BookCover.vue'
import type { Book } from '@/types/library'

defineProps<{
  book: Book
}>()
</script>

<template>
  <ULink
    :to="route('library.books.show', { book: book.id }, false)"
    raw
    data-testid="book"
    class="group block rounded-lg focus-visible:outline-2 focus-visible:outline-primary"
  >
    <BookCover
      :url="book.cover_url"
      :title="book.title"
    />
    <p class="mt-2 line-clamp-2 font-medium text-highlighted group-hover:underline">
      {{ book.title }}
    </p>
    <p class="truncate text-sm text-muted">
      {{ book.author }}
    </p>
  </ULink>
</template>
