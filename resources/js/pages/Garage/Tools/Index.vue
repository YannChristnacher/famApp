<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import ToolCard from '@/components/Garage/ToolCard.vue'
import type { Brand, Tool, ToolFilters, ToolShelf } from '@/types/garage'

const props = defineProps<{
  /** The tools of the signed-in member (filtered), by name. */
  tools: Tool[]
  /** Their shelves, by position, for the filter. */
  shelves: ToolShelf[]
  /** The brands of their tools, by name, for the filter. */
  brands: Brand[]
  filters: ToolFilters
}>()

const breadcrumb = [
  { label: 'Garage' },
  { label: 'Mes outils' },
]

/** Query of the list for the given filters (null ones are left out). */
function listUrl(filters: ToolFilters): string {
  const query: Record<string, number> = {}
  if (filters.shelf !== null) {
    query.shelf = filters.shelf
  }
  if (filters.brand !== null) {
    query.brand = filters.brand
  }

  return route('garage.tools.index', query, false)
}

const shelfFilters = computed(() => [
  { key: 'all', label: 'Toutes les étagères', to: listUrl({ ...props.filters, shelf: null }), active: props.filters.shelf === null },
  ...props.shelves.map((shelf) => ({
    key: `shelf-${shelf.id}`,
    label: shelf.name,
    to: listUrl({ ...props.filters, shelf: shelf.id }),
    active: shelf.id === props.filters.shelf,
  })),
])

const ALL_BRANDS = 'all'

const brandItems = computed(() => [
  { label: 'Toutes les marques', value: ALL_BRANDS },
  ...props.brands.map((brand) => ({ label: brand.name, value: String(brand.id) })),
])

const brandFilter = computed(() => props.filters.brand === null ? ALL_BRANDS : String(props.filters.brand))

function filterByBrand(value: string): void {
  router.get(listUrl({ ...props.filters, brand: value === ALL_BRANDS ? null : Number(value) }), {}, { preserveScroll: true })
}

const filtered = computed(() => props.filters.shelf !== null || props.filters.brand !== null)
</script>

<template>
  <DashboardLayout
    title="Mes outils"
    :breadcrumb="breadcrumb"
  >
    <div class="mx-auto w-full max-w-6xl space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <nav
            v-if="shelves.length > 0"
            aria-label="Filtrer par étagère"
            class="flex flex-wrap gap-2"
          >
            <UButton
              v-for="item in shelfFilters"
              :key="item.key"
              :to="item.to"
              :label="item.label"
              :color="item.active ? 'primary' : 'neutral'"
              :variant="item.active ? 'solid' : 'outline'"
              :aria-current="item.active ? 'page' : undefined"
              size="sm"
              data-testid="shelf-filter"
            />
          </nav>
          <USelect
            v-if="brands.length > 0"
            :model-value="brandFilter"
            :items="brandItems"
            aria-label="Filtrer par marque"
            size="sm"
            class="w-48"
            data-testid="brand-filter"
            @update:model-value="filterByBrand"
          />
        </div>
        <div class="ms-auto flex gap-2">
          <UButton
            :to="route('garage.shelves.index', undefined, false)"
            icon="i-lucide-toolbox"
            color="neutral"
            variant="ghost"
          >
            Mes étagères
          </UButton>
          <UButton
            :to="route('garage.tools.create', undefined, false)"
            icon="i-lucide-plus"
          >
            Ajouter un outil
          </UButton>
        </div>
      </div>

      <UEmpty
        v-if="tools.length === 0 && !filtered"
        icon="i-lucide-wrench"
        title="Vous n'avez encore aucun outil."
        description="Ajoutez votre premier outil pour le montrer à la famille."
        :actions="[{ label: 'Ajouter un outil', icon: 'i-lucide-plus', to: route('garage.tools.create', undefined, false) }]"
        variant="naked"
      />
      <UEmpty
        v-else-if="tools.length === 0"
        icon="i-lucide-wrench"
        title="Aucun outil ne correspond à ces filtres."
        :actions="[{ label: 'Voir tous mes outils', color: 'neutral', variant: 'outline', to: route('garage.tools.index', undefined, false) }]"
        variant="naked"
      />

      <ul
        v-else
        class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
      >
        <li
          v-for="tool in tools"
          :key="tool.id"
        >
          <ToolCard :tool="tool" />
        </li>
      </ul>
    </div>
  </DashboardLayout>
</template>
