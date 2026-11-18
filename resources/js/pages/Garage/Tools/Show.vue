<script setup lang="ts">
import { computed, ref } from 'vue'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import ToolDeleteModal from '@/components/Garage/ToolDeleteModal.vue'
import ToolThumbnail from '@/components/Garage/ToolThumbnail.vue'
import type { ToolDetails } from '@/types/garage'

const props = defineProps<{
  tool: ToolDetails
}>()

const breadcrumb = computed(() => [
  { label: 'Garage' },
  { label: 'Mes outils', to: route('garage.tools.index', undefined, false) },
  { label: props.tool.name },
])

const deleteOpen = ref(false)
</script>

<template>
  <DashboardLayout
    :title="tool.name"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-4xl">
      <div class="flex flex-col gap-6 md:flex-row">
        <div class="w-full shrink-0 md:w-80">
          <UCarousel
            v-if="tool.photos.length > 0"
            v-slot="{ item, index }"
            :items="tool.photos"
            :arrows="tool.photos.length > 1"
            :dots="tool.photos.length > 1"
            :prev="{ 'aria-label': 'Photo précédente' }"
            :next="{ 'aria-label': 'Photo suivante' }"
            data-testid="tool-gallery"
            class="w-full"
          >
            <img
              :src="item.url"
              :alt="`Photo ${index + 1} de ${tool.name}`"
              class="aspect-square w-full rounded-md object-cover"
            >
          </UCarousel>
          <ToolThumbnail
            v-else
            :url="null"
            :name="tool.name"
          />
        </div>

        <div class="min-w-0 flex-1 space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-highlighted">
              {{ tool.name }}
            </h2>
            <p
              class="text-lg text-muted"
              data-testid="tool-brand"
            >
              {{ tool.brand?.name ?? 'Marque non renseignée' }}
            </p>
          </div>

          <dl class="space-y-3 text-sm">
            <div>
              <dt class="font-medium text-highlighted">
                Accessoires
              </dt>
              <dd class="mt-1 text-muted">
                <ul
                  v-if="tool.accessories.length > 0"
                  class="list-inside list-disc"
                >
                  <li
                    v-for="accessory in tool.accessories"
                    :key="accessory.id"
                    data-testid="tool-accessory"
                  >
                    {{ accessory.name }}
                  </li>
                </ul>
                <span v-else>Aucun accessoire</span>
              </dd>
            </div>
            <div>
              <dt class="font-medium text-highlighted">
                Étagères
              </dt>
              <dd class="mt-1 flex flex-wrap gap-2">
                <UButton
                  v-for="shelf in tool.shelves"
                  :key="shelf.id"
                  :to="route('garage.tools.index', { shelf: shelf.id }, false)"
                  :label="shelf.name"
                  icon="i-lucide-toolbox"
                  color="neutral"
                  variant="subtle"
                  size="xs"
                  data-testid="tool-shelf"
                />
                <span
                  v-if="tool.shelves.length === 0"
                  class="text-muted"
                >Rangé sur aucune étagère</span>
              </dd>
            </div>
          </dl>

          <div class="flex flex-wrap gap-2 pt-2">
            <UButton
              :to="route('garage.tools.edit', { tool: tool.id }, false)"
              icon="i-lucide-pencil"
            >
              Modifier
            </UButton>
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="outline"
              @click="deleteOpen = true"
            >
              Supprimer
            </UButton>
          </div>
        </div>
      </div>
    </UCard>

    <ToolDeleteModal
      v-model:open="deleteOpen"
      :tool="tool"
    />
  </DashboardLayout>
</template>
