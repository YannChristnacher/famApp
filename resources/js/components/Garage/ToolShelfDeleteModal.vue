<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { ToolShelf } from '@/types/garage'

const props = defineProps<{
  /** The shelf to delete; the modal is closed when null. */
  shelf: ToolShelf | null
}>()

const open = defineModel<boolean>('open', { required: true })

const deleting = ref(false)

function confirm(): void {
  if (props.shelf === null || deleting.value) {
    return
  }

  router.delete(route('garage.shelves.destroy', { shelf: props.shelf.id }, false), {
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
    :description="`L'étagère « ${shelf?.name ?? ''} » sera supprimée définitivement.`"
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
