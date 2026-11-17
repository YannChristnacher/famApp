<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BookshelfDeleteModal from '@/components/Library/BookshelfDeleteModal.vue'
import BookshelfRenameModal from '@/components/Library/BookshelfRenameModal.vue'
import type { Bookshelf, BookshelfMoveDirection } from '@/types/library'

defineProps<{
  /** The bookshelves of the signed-in member, by position. */
  bookshelves: Bookshelf[]
}>()

const breadcrumb = [{ label: 'Bibliothèque' }, { label: 'Étagères' }]

const createForm = useForm({ name: '' })

function create(): void {
  createForm.post(route('library.bookshelves.store', undefined, false), {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  })
}

const renamed = ref<Bookshelf | null>(null)
const renameOpen = ref(false)

function rename(bookshelf: Bookshelf): void {
  renamed.value = bookshelf
  renameOpen.value = true
}

const deleted = ref<Bookshelf | null>(null)
const deleteOpen = ref(false)

function remove(bookshelf: Bookshelf): void {
  deleted.value = bookshelf
  deleteOpen.value = true
}

const moving = ref(false)

function move(bookshelf: Bookshelf, direction: BookshelfMoveDirection): void {
  if (moving.value) {
    return
  }

  router.patch(route('library.bookshelves.move', { bookshelf: bookshelf.id }, false), { direction }, {
    preserveScroll: true,
    onStart: () => { moving.value = true },
    onFinish: () => { moving.value = false },
  })
}
</script>

<template>
  <DashboardLayout
    title="Ma bibliothèque"
    :breadcrumb="breadcrumb"
  >
    <div class="mx-auto w-full max-w-3xl space-y-6">
      <div class="flex justify-end">
        <UButton
          :to="route('library.books.index', undefined, false)"
          icon="i-lucide-book-open"
          color="neutral"
          variant="ghost"
        >
          Mes livres
        </UButton>
      </div>

      <UCard>
        <UForm
          :state="createForm"
          class="flex flex-col gap-3 sm:flex-row sm:items-start"
          @submit="create"
        >
          <UFormField
            label="Nouvelle étagère"
            name="name"
            required
            :error="createForm.errors.name"
            class="flex-1"
          >
            <UInput
              v-model="createForm.name"
              name="name"
              placeholder="Fantasy, Coup de cœur…"
              autocomplete="off"
              class="w-full"
            />
          </UFormField>
          <UButton
            type="submit"
            icon="i-lucide-plus"
            :loading="createForm.processing"
            class="sm:mt-6"
          >
            Ajouter
          </UButton>
        </UForm>
      </UCard>

      <UEmpty
        v-if="bookshelves.length === 0"
        icon="i-lucide-library-big"
        title="Votre bibliothèque est vide."
        description="Créez votre première étagère avec le formulaire ci-dessus pour y ranger vos livres."
        variant="naked"
      />

      <UCard
        v-else
        :ui="{ body: 'p-0 sm:p-0' }"
      >
        <ul class="divide-y divide-default">
          <li
            v-for="(bookshelf, index) in bookshelves"
            :key="bookshelf.id"
            data-testid="bookshelf"
            class="flex items-center gap-3 px-4 py-3"
          >
            <UIcon
              name="i-lucide-library"
              class="size-5 shrink-0 text-muted"
            />
            <span class="min-w-0 flex-1 truncate font-medium text-highlighted">{{ bookshelf.name }}</span>

            <div class="flex shrink-0 gap-1">
              <UButton
                :to="route('library.books.index', { bookshelf: bookshelf.id }, false)"
                icon="i-lucide-book-open"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Voir les livres de l'étagère ${bookshelf.name}`"
              />
              <UButton
                icon="i-lucide-arrow-up"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Monter l'étagère ${bookshelf.name}`"
                :disabled="index === 0 || moving"
                @click="move(bookshelf, 'up')"
              />
              <UButton
                icon="i-lucide-arrow-down"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Descendre l'étagère ${bookshelf.name}`"
                :disabled="index === bookshelves.length - 1 || moving"
                @click="move(bookshelf, 'down')"
              />
              <UButton
                icon="i-lucide-pencil"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Renommer l'étagère ${bookshelf.name}`"
                @click="rename(bookshelf)"
              />
              <UButton
                icon="i-lucide-trash-2"
                color="error"
                variant="ghost"
                size="sm"
                :aria-label="`Supprimer l'étagère ${bookshelf.name}`"
                @click="remove(bookshelf)"
              />
            </div>
          </li>
        </ul>
      </UCard>
    </div>

    <BookshelfRenameModal
      v-model:open="renameOpen"
      :bookshelf="renamed"
    />
    <BookshelfDeleteModal
      v-model:open="deleteOpen"
      :bookshelf="deleted"
    />
  </DashboardLayout>
</template>
