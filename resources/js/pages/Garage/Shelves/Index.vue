<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import ToolShelfDeleteModal from '@/components/Garage/ToolShelfDeleteModal.vue'
import ToolShelfRenameModal from '@/components/Garage/ToolShelfRenameModal.vue'
import type { ToolShelf, ToolShelfMoveDirection } from '@/types/garage'

defineProps<{
  /** The tool shelves of the signed-in member, by position. */
  shelves: ToolShelf[]
}>()

const breadcrumb = [{ label: 'Garage' }, { label: 'Étagères' }]

const createForm = useForm({ name: '' })

function create(): void {
  createForm.post(route('garage.shelves.store', undefined, false), {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  })
}

const renamed = ref<ToolShelf | null>(null)
const renameOpen = ref(false)

function rename(shelf: ToolShelf): void {
  renamed.value = shelf
  renameOpen.value = true
}

const deleted = ref<ToolShelf | null>(null)
const deleteOpen = ref(false)

function remove(shelf: ToolShelf): void {
  deleted.value = shelf
  deleteOpen.value = true
}

const moving = ref(false)

function move(shelf: ToolShelf, direction: ToolShelfMoveDirection): void {
  if (moving.value) {
    return
  }

  router.patch(route('garage.shelves.move', { shelf: shelf.id }, false), { direction }, {
    preserveScroll: true,
    onStart: () => { moving.value = true },
    onFinish: () => { moving.value = false },
  })
}
</script>

<template>
  <DashboardLayout
    title="Mon garage"
    :breadcrumb="breadcrumb"
  >
    <div class="mx-auto w-full max-w-3xl space-y-6">
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
              placeholder="Électroportatif, Manuel…"
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
        v-if="shelves.length === 0"
        icon="i-lucide-wrench"
        title="Votre garage est vide."
        description="Créez votre première étagère avec le formulaire ci-dessus pour y ranger vos outils."
        variant="naked"
      />

      <UCard
        v-else
        :ui="{ body: 'p-0 sm:p-0' }"
      >
        <ul class="divide-y divide-default">
          <li
            v-for="(shelf, index) in shelves"
            :key="shelf.id"
            data-testid="tool-shelf"
            class="flex items-center gap-3 px-4 py-3"
          >
            <UIcon
              name="i-lucide-toolbox"
              class="size-5 shrink-0 text-muted"
            />
            <span class="min-w-0 flex-1 truncate font-medium text-highlighted">{{ shelf.name }}</span>

            <div class="flex shrink-0 gap-1">
              <UButton
                icon="i-lucide-arrow-up"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Monter l'étagère ${shelf.name}`"
                :disabled="index === 0 || moving"
                @click="move(shelf, 'up')"
              />
              <UButton
                icon="i-lucide-arrow-down"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Descendre l'étagère ${shelf.name}`"
                :disabled="index === shelves.length - 1 || moving"
                @click="move(shelf, 'down')"
              />
              <UButton
                icon="i-lucide-pencil"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Renommer l'étagère ${shelf.name}`"
                @click="rename(shelf)"
              />
              <UButton
                icon="i-lucide-trash-2"
                color="error"
                variant="ghost"
                size="sm"
                :aria-label="`Supprimer l'étagère ${shelf.name}`"
                @click="remove(shelf)"
              />
            </div>
          </li>
        </ul>
      </UCard>
    </div>

    <ToolShelfRenameModal
      v-model:open="renameOpen"
      :shelf="renamed"
    />
    <ToolShelfDeleteModal
      v-model:open="deleteOpen"
      :shelf="deleted"
    />
  </DashboardLayout>
</template>
