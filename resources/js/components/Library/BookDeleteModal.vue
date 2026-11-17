<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { Book } from '@/types/library'

const props = defineProps<{
  book: Book
}>()

const open = defineModel<boolean>('open', { required: true })

const deleting = ref(false)

function confirm(): void {
  if (deleting.value) {
    return
  }

  router.delete(route('library.books.destroy', { book: props.book.id }, false), {
    onStart: () => { deleting.value = true },
    onSuccess: () => { open.value = false },
    onFinish: () => { deleting.value = false },
  })
}
</script>

<template>
  <UModal
    v-model:open="open"
    title="Supprimer le livre"
    :description="`Le livre « ${book.title} » et sa couverture seront supprimés définitivement.`"
  >
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          color="neutral"
          variant="ghost"
          @click="open = false"
        >
          Annuler
        </UButton>
        <UButton
          color="error"
          icon="i-lucide-trash-2"
          :loading="deleting"
          @click="confirm"
        >
          Supprimer
        </UButton>
      </div>
    </template>
  </UModal>
</template>
