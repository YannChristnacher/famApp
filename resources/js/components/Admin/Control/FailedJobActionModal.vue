<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { FailedJob, FailedJobAction } from '@/types/admin'

const props = defineProps<{
  job: FailedJob | null
  action: FailedJobAction
}>()

const open = defineModel<boolean>('open', { required: true })

const sending = ref(false)

const texts = computed(() => (props.action === 'retry'
  ? {
      title: 'Relancer le job',
      description: `Le job « ${props.job?.job ?? ''} » sera remis dans la file « ${props.job?.queue ?? ''} » et retiré des jobs en échec.`,
      confirm: 'Relancer',
      icon: 'i-lucide-rotate-ccw',
      color: 'primary' as const,
    }
  : {
      title: 'Supprimer le job en échec',
      description: `Le job « ${props.job?.job ?? ''} » sera supprimé définitivement, sans être relancé.`,
      confirm: 'Supprimer',
      icon: 'i-lucide-trash-2',
      color: 'error' as const,
    }))

function confirm(): void {
  if (sending.value || props.job === null) {
    return
  }

  const options = {
    preserveScroll: true,
    onStart: () => { sending.value = true },
    onSuccess: () => { open.value = false },
    onFinish: () => { sending.value = false },
  }

  if (props.action === 'retry') {
    router.post(route('admin.failed-jobs.retry', { uuid: props.job.uuid }, false), {}, options)
  } else {
    router.delete(route('admin.failed-jobs.destroy', { uuid: props.job.uuid }, false), options)
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="texts.title"
    :description="texts.description"
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
          :color="texts.color"
          :icon="texts.icon"
          :loading="sending"
          @click="confirm"
        >
          {{ texts.confirm }}
        </UButton>
      </div>
    </template>
  </UModal>
</template>
