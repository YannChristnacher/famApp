<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { Tool } from '@/types/garage'

const props = defineProps<{
  tool: Tool
}>()

const open = defineModel<boolean>('open', { required: true })

const deleting = ref(false)

function confirm(): void {
  if (deleting.value) {
    return
  }

  router.delete(route('garage.tools.destroy', { tool: props.tool.id }, false), {
    onStart: () => { deleting.value = true },
    onSuccess: () => { open.value = false },
    onFinish: () => { deleting.value = false },
  })
}
</script>

<template>
  <UModal
    v-model:open="open"
    title="Supprimer l’outil"
    :description="`L’outil « ${tool.name} », ses photos et ses accessoires seront supprimés définitivement.`"
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
