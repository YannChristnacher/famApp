<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import ToolAccessoriesField from '@/components/Garage/ToolAccessoriesField.vue'
import ToolPhotosField from '@/components/Garage/ToolPhotosField.vue'
import {
  accessoryRow,
  nextRowKey,
  normalizedBrand,
  type AccessoryRow,
  type PhotoRow,
} from '@/support/garage/toolFormRows'
import type { Brand, ToolDetails, ToolShelf } from '@/types/garage'

const props = defineProps<{
  /** The edited tool, null to create one. */
  tool: ToolDetails | null
  /** The tool shelves of the member, by position. */
  shelves: ToolShelf[]
  /** Every brand of the family, by name. */
  brands: Brand[]
}>()

/** Limits of App\Http\Requests\Garage\ToolRequest. */
const MAX_PHOTOS = 8
const MAX_ACCESSORIES = 30
const COMMENT_MAX_LENGTH = 2000

interface ToolFormData {
  name: string
  /** Typed brand: matched by the server on the shared brands, created otherwise. */
  brand: string | null
  /** Trimmed by the server, saved as null when blank. */
  comment: string
  shelf_ids: number[]
  accessories: AccessoryRow[]
  photos: PhotoRow[]
}

const form = useForm<ToolFormData>({
  name: props.tool?.name ?? '',
  brand: props.tool?.brand?.name ?? null,
  comment: props.tool?.comment ?? '',
  shelf_ids: props.tool?.shelves.map((shelf) => shelf.id) ?? [],
  accessories: props.tool?.accessories.map((accessory) => accessoryRow(accessory.name)) ?? [],
  photos: props.tool?.photos.map((photo) => ({ key: nextRowKey(), id: photo.id, url: photo.url })) ?? [],
})

/** Names offered by the brand menu: the shared brands and the ones created here. */
const brandNames = ref<string[]>(props.brands.map((brand) => brand.name))

/** A typed brand that already exists (case and spaces aside) selects it. */
function createBrand(typed: string): void {
  const name = typed.trim()
  if (name === '') {
    return
  }

  const existing = brandNames.value.find((brand) => normalizedBrand(brand) === normalizedBrand(name))
  if (existing === undefined) {
    brandNames.value = [...brandNames.value, name]
  }
  form.brand = existing ?? name
}

const errors = computed(() => form.errors as Record<string, string | undefined>)

/** Errors of the list itself or of one of its items (shelf_ids.0…). */
const shelfError = computed(() => Object.entries(errors.value)
  .find(([field]) => field === 'shelf_ids' || field.startsWith('shelf_ids.'))?.[1])

const cancelUrl = computed(() => props.tool === null
  ? route('garage.tools.index', undefined, false)
  : route('garage.tools.show', { tool: props.tool.id }, false))

/** What the server reads: accessory names and photos ({id} kept, {file} new), in order. */
function payload(data: ToolFormData) {
  return {
    name: data.name,
    brand: data.brand,
    comment: data.comment,
    shelf_ids: data.shelf_ids,
    accessories: data.accessories.map((row) => row.name),
    photos: data.photos.map((row) => ('file' in row ? { file: row.file } : { id: row.id })),
  }
}

function submit(): void {
  if (props.tool === null) {
    form
      .transform(payload)
      .post(route('garage.tools.store', undefined, false), { preserveScroll: true })

    return
  }

  // Files cannot be sent with PATCH (PHP reads multipart bodies on POST only).
  form
    .transform((data) => ({ ...payload(data), _method: 'patch' }))
    .post(route('garage.tools.update', { tool: props.tool.id }, false), { preserveScroll: true })
}
</script>

<template>
  <UForm
    :state="form"
    class="space-y-6"
    @submit="submit"
  >
    <div class="grid gap-4 md:grid-cols-2">
      <UFormField
        label="Nom"
        name="name"
        required
        :error="form.errors.name"
      >
        <UInput
          v-model="form.name"
          name="name"
          placeholder="Perceuse visseuse, scie sauteuse…"
          autocomplete="off"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Marque"
        name="brand"
        help="Choisissez une marque de la famille ou tapez-en une nouvelle."
        :error="form.errors.brand"
      >
        <USelectMenu
          v-model="form.brand"
          :items="brandNames"
          create-item
          clear
          name="brand"
          placeholder="Aucune marque"
          :search-input="{ placeholder: 'Rechercher ou créer…' }"
          data-testid="brand-select"
          class="w-full"
          @create="createBrand"
        />
      </UFormField>
    </div>

    <UFormField
      label="Étagères"
      name="shelf_ids"
      :help="shelves.length === 0 ? 'Vous n’avez pas encore d’étagère : l’outil restera hors étagère.' : undefined"
      :error="shelfError"
    >
      <USelectMenu
        v-model="form.shelf_ids"
        :items="shelves"
        value-key="id"
        label-key="name"
        multiple
        name="shelf_ids"
        placeholder="Choisir une ou plusieurs étagères"
        :search-input="{ placeholder: 'Filtrer…' }"
        :disabled="shelves.length === 0"
        data-testid="shelf-select"
        class="w-full"
      />
    </UFormField>

    <ToolAccessoriesField
      v-model="form.accessories"
      :errors="errors"
      :max="MAX_ACCESSORIES"
    />

    <UFormField
      label="Commentaire"
      name="comment"
      help="Facultatif, visible par toute la famille."
      :error="form.errors.comment"
    >
      <template #hint>
        <span data-testid="comment-counter">{{ form.comment.length }} / {{ COMMENT_MAX_LENGTH }}</span>
      </template>
      <UTextarea
        v-model="form.comment"
        name="comment"
        :maxlength="COMMENT_MAX_LENGTH"
        :rows="3"
        autoresize
        placeholder="État, réglages, consommables…"
        class="w-full"
      />
    </UFormField>

    <ToolPhotosField
      v-model="form.photos"
      :errors="errors"
      :max="MAX_PHOTOS"
      :tool-name="form.name"
    />

    <div class="flex justify-end gap-2">
      <UButton
        :to="cancelUrl"
        color="neutral"
        variant="ghost"
      >
        Annuler
      </UButton>
      <UButton
        type="submit"
        icon="i-lucide-check"
        :loading="form.processing"
      >
        {{ tool === null ? 'Ajouter l’outil' : 'Enregistrer' }}
      </UButton>
    </div>
  </UForm>
</template>
