<script setup lang="ts">
import { nextTick } from 'vue'
import { accessoryRow, moved, type AccessoryRow } from '@/support/garage/toolFormRows'

const props = defineProps<{
  /** Errors of the form, to show the ones of each line (accessories.0…). */
  errors: Record<string, string | undefined>
  max: number
}>()

const rows = defineModel<AccessoryRow[]>({ required: true })

async function add(): Promise<void> {
  if (rows.value.length >= props.max) {
    return
  }

  const row = accessoryRow()
  rows.value = [...rows.value, row]
  await nextTick()
  document.querySelector<HTMLInputElement>(`input[name="accessories.${rows.value.length - 1}"]`)?.focus()
}

function remove(index: number): void {
  rows.value = rows.value.filter((_, rowIndex) => rowIndex !== index)
}

function move(index: number, offset: -1 | 1): void {
  rows.value = moved(rows.value, index, offset)
}
</script>

<template>
  <UFormField
    label="Accessoires"
    name="accessories"
    :error="errors.accessories"
    :help="rows.length === 0 ? 'Mallette, batterie, lames… une ligne par accessoire.' : undefined"
  >
    <ul
      v-if="rows.length > 0"
      class="space-y-2"
    >
      <li
        v-for="(row, index) in rows"
        :key="row.key"
        data-testid="accessory-row"
      >
        <div class="flex items-center gap-1">
          <UInput
            v-model="row.name"
            :name="`accessories.${index}`"
            :aria-label="`Accessoire ${index + 1}`"
            :color="errors[`accessories.${index}`] ? 'error' : undefined"
            :highlight="errors[`accessories.${index}`] !== undefined"
            placeholder="Nom de l’accessoire"
            autocomplete="off"
            class="min-w-0 flex-1"
          />
          <UButton
            icon="i-lucide-arrow-up"
            color="neutral"
            variant="ghost"
            size="sm"
            :aria-label="`Monter l’accessoire ${index + 1}`"
            :disabled="index === 0"
            @click="move(index, -1)"
          />
          <UButton
            icon="i-lucide-arrow-down"
            color="neutral"
            variant="ghost"
            size="sm"
            :aria-label="`Descendre l’accessoire ${index + 1}`"
            :disabled="index === rows.length - 1"
            @click="move(index, 1)"
          />
          <UButton
            icon="i-lucide-x"
            color="neutral"
            variant="ghost"
            size="sm"
            :aria-label="`Retirer l’accessoire ${index + 1}`"
            @click="remove(index)"
          />
        </div>
        <p
          v-if="errors[`accessories.${index}`]"
          class="mt-1 text-sm text-error"
        >
          {{ errors[`accessories.${index}`] }}
        </p>
      </li>
    </ul>
    <UButton
      icon="i-lucide-plus"
      color="neutral"
      variant="outline"
      size="sm"
      class="mt-2"
      :disabled="rows.length >= max"
      data-testid="accessory-add"
      @click="add"
    >
      Ajouter un accessoire
    </UButton>
  </UFormField>
</template>
