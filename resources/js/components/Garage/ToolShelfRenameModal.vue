<script setup lang="ts">
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { ToolShelf } from '@/types/garage'

const props = defineProps<{
  /** The shelf to rename; the modal is closed when null. */
  shelf: ToolShelf | null
}>()

const open = defineModel<boolean>('open', { required: true })

const form = useForm({ name: '' })

watch(() => props.shelf, (shelf) => {
  form.name = shelf?.name ?? ''
  form.clearErrors()
}, { immediate: true })

function submit(): void {
  if (props.shelf === null) {
    return
  }

  form.patch(route('garage.shelves.update', { shelf: props.shelf.id }, false), {
    preserveScroll: true,
    onSuccess: () => { open.value = false },
  })
}
</script>

<template>
  <UModal
    v-model:open="open"
    title="Renommer l'étagère"
    description="Choisissez un nouveau nom pour cette étagère du garage."
  >
    <template #body>
      <UForm
        :state="form"
        class="space-y-4"
        @submit="submit"
      >
        <UFormField
          label="Nom de l'étagère"
          name="name"
          required
          :error="form.errors.name"
        >
          <UInput
            v-model="form.name"
            name="rename"
            autocomplete="off"
            class="w-full"
          />
        </UFormField>

        <div class="flex justify-end gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            @click="open = false"
          >
            Annuler
          </UButton>
          <UButton
            type="submit"
            icon="i-lucide-pencil"
            :loading="form.processing"
          >
            Renommer
          </UButton>
        </div>
      </UForm>
    </template>
  </UModal>
</template>
