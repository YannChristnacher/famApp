<script setup lang="ts">
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import type { Bookshelf } from '@/types/library'

const props = defineProps<{
  /** The bookshelf to rename; the modal is closed when null. */
  bookshelf: Bookshelf | null
}>()

const open = defineModel<boolean>('open', { required: true })

const form = useForm({ name: '' })

watch(() => props.bookshelf, (bookshelf) => {
  form.name = bookshelf?.name ?? ''
  form.clearErrors()
}, { immediate: true })

function submit(): void {
  if (props.bookshelf === null) {
    return
  }

  form.patch(route('library.bookshelves.update', { bookshelf: props.bookshelf.id }, false), {
    preserveScroll: true,
    onSuccess: () => { open.value = false },
  })
}
</script>

<template>
  <UModal
    v-model:open="open"
    title="Renommer l'étagère"
    description="Choisissez un nouveau nom pour cette étagère."
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
