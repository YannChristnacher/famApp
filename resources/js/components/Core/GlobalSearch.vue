<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import type { CommandPaletteGroup, CommandPaletteItem } from '@nuxt/ui'
import { useGlobalSearch } from '@/composables/useGlobalSearch'
import type { SearchGroup, SearchResult } from '@/types/search'

const open = defineModel<boolean>('open', { default: false })
const searchTerm = ref('')
const { groups, loading, error, tooShort, query } = useGlobalSearch(searchTerm)

function toItem(result: SearchResult, group: SearchGroup): CommandPaletteItem {
  return {
    label: result.label,
    description: result.description ?? undefined,
    ...(result.image ? { avatar: { src: result.image, alt: '' } } : { icon: group.icon }),
    onSelect: () => router.visit(result.url),
  }
}

// The server already filtered and ordered the results: no local filtering.
const paletteGroups = computed<CommandPaletteGroup[]>(() => groups.value.map((group) => ({
  id: group.key,
  label: group.label,
  ignoreFilter: true,
  items: group.results.map((result) => toItem(result, group)),
})))

const emptyMessage = computed(() => {
  if (error.value) {
    return error.value
  }
  if (tooShort.value) {
    return 'Tapez au moins 2 caractères pour chercher un livre, un outil ou un membre.'
  }

  return loading.value ? 'Recherche en cours…' : `Aucun résultat pour « ${query.value} ».`
})
</script>

<template>
  <UDashboardSearch
    v-model:open="open"
    v-model:search-term="searchTerm"
    :groups="paletteGroups"
    :loading="loading"
    :color-mode="false"
    placeholder="Rechercher un livre, un outil, un membre…"
  >
    <template #empty>
      <p class="py-6 text-center text-sm text-muted">
        {{ emptyMessage }}
      </p>
    </template>
  </UDashboardSearch>
</template>
