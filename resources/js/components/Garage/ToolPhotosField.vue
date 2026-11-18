<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { moved, newPhotoRow, type PhotoRow } from '@/support/garage/toolFormRows'

const props = defineProps<{
  /** Errors of the form, to show the ones of the photos (photos, photos.1.file…). */
  errors: Record<string, string | undefined>
  max: number
  toolName: string
}>()

const rows = defineModel<PhotoRow[]>({ required: true })

/** Files picked in UFileUpload, moved at once into the ordered rows. */
const picked = ref<File[] | null>(null)

const remaining = computed(() => props.max - rows.value.length)

const tooMany = ref(false)

function add(files: File[] | null | undefined): void {
  const chosen = files ?? []
  tooMany.value = chosen.length > remaining.value
  rows.value = [...rows.value, ...chosen.slice(0, Math.max(0, remaining.value)).map(newPhotoRow)]
  picked.value = null
}

function revoke(row: PhotoRow): void {
  if ('file' in row) {
    URL.revokeObjectURL(row.url)
  }
}

function remove(index: number): void {
  const row = rows.value[index]
  if (row !== undefined) {
    revoke(row)
  }
  rows.value = rows.value.filter((_, rowIndex) => rowIndex !== index)
  tooMany.value = false
}

function move(index: number, offset: -1 | 1): void {
  rows.value = moved(rows.value, index, offset)
}

onBeforeUnmount(() => rows.value.forEach(revoke))

/** The list error, or the first error of one photo (photos.2.file…). */
const error = computed(() => props.errors.photos ?? Object.entries(props.errors)
  .find(([field]) => field.startsWith('photos.'))?.[1])
</script>

<template>
  <UFormField
    label="Photos"
    name="photos"
    :error="error"
    :help="`La première photo sert de vignette. ${max} photos maximum.`"
  >
    <ol
      v-if="rows.length > 0"
      class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4"
    >
      <li
        v-for="(row, index) in rows"
        :key="row.key"
        data-testid="photo-row"
        class="space-y-1"
      >
        <div class="relative aspect-square overflow-hidden rounded-md bg-elevated">
          <img
            :src="row.url"
            :alt="`Photo ${index + 1} de ${toolName || 'l’outil'}`"
            class="size-full object-cover"
          >
          <UBadge
            v-if="index === 0"
            label="Vignette"
            color="primary"
            size="sm"
            class="absolute start-1 top-1"
          />
        </div>
        <div class="flex justify-center gap-1">
          <UButton
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="ghost"
            size="xs"
            :aria-label="`Avancer la photo ${index + 1}`"
            :disabled="index === 0"
            @click="move(index, -1)"
          />
          <UButton
            icon="i-lucide-arrow-right"
            color="neutral"
            variant="ghost"
            size="xs"
            :aria-label="`Reculer la photo ${index + 1}`"
            :disabled="index === rows.length - 1"
            @click="move(index, 1)"
          />
          <UButton
            icon="i-lucide-trash-2"
            color="error"
            variant="ghost"
            size="xs"
            :aria-label="`Retirer la photo ${index + 1}`"
            @click="remove(index)"
          />
        </div>
      </li>
    </ol>
    <UAlert
      v-if="tooMany"
      color="warning"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="`Seules ${max} photos peuvent être gardées : les suivantes ont été ignorées.`"
      class="mb-3"
      data-testid="photos-too-many"
    />
    <UFileUpload
      v-if="remaining > 0"
      :model-value="picked"
      multiple
      :preview="false"
      name="new_photos"
      accept="image/jpeg,image/png,image/webp"
      label="Ajouter des photos"
      :description="`JPEG, PNG ou WebP, 10 Mo maximum par photo. Encore ${remaining} possible${remaining > 1 ? 's' : ''}.`"
      class="min-h-32 w-full"
      @update:model-value="add"
    />
  </UFormField>
</template>
