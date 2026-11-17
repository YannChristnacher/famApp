<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { Bookshelf } from '@/types/library'

const props = defineProps<{
  /** The bookshelf to delete; the modal is closed when null. */
  bookshelf: Bookshelf | null
}>()

const open = defineModel<boolean>('open', { required: true })

const deleting = ref(false)

function confirm(): void {
  if (props.bookshelf === null || deleting.value) {
    return
  }

  router.delete(route('library.bookshelves.destroy', { bookshelf: props.bookshelf.id }, false), {
    preserveScroll: true,
    onStart: () => { deleting.value = true },
    onSuccess: () => { open.value = false },
    onFinish: () => { deleting.value = false },
  })
}
</script>

<template>
  <UModal
    v-model:open="open"
    title="Supprimer l'étagère"
    :description="`L'étagère « ${bookshelf?.name ?? ''} » sera supprimée définitivement.`"
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
