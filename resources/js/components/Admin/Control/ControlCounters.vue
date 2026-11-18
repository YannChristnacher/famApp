<script setup lang="ts">
import { computed } from 'vue'
import { route } from 'ziggy-js'
import type { ControlCounts } from '@/types/admin'

const props = defineProps<{
  pendingAccountRequests: number
  counts: ControlCounts
}>()

const counters = computed(() => [
  { label: 'Membres', value: props.counts.members, icon: 'i-lucide-users' },
  { label: 'Invitations en attente', value: props.counts.invited, icon: 'i-lucide-mail' },
  { label: 'Livres', value: props.counts.books, icon: 'i-lucide-book' },
  { label: 'Outils', value: props.counts.tools, icon: 'i-lucide-wrench' },
  { label: 'Étagères de livres', value: props.counts.bookshelves, icon: 'i-lucide-library' },
  { label: 'Étagères d\'outils', value: props.counts.toolShelves, icon: 'i-lucide-warehouse' },
  { label: 'Marques', value: props.counts.brands, icon: 'i-lucide-tag' },
])
</script>

<template>
  <div
    data-testid="control-counters"
    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
  >
    <UPageCard
      data-testid="control-pending-requests"
      title="Demandes en attente"
      icon="i-lucide-inbox"
      :highlight="pendingAccountRequests > 0"
      highlight-color="warning"
      variant="subtle"
    >
      <div class="flex items-center justify-between gap-2">
        <span class="text-3xl font-semibold">{{ pendingAccountRequests }}</span>
        <UButton
          :to="route('admin.account-requests.index', undefined, false)"
          size="sm"
          variant="outline"
          trailing-icon="i-lucide-arrow-right"
        >
          Voir les demandes
        </UButton>
      </div>
    </UPageCard>

    <UPageCard
      v-for="counter in counters"
      :key="counter.label"
      :title="counter.label"
      :icon="counter.icon"
      variant="outline"
    >
      <span class="text-2xl font-semibold">{{ counter.value }}</span>
    </UPageCard>
  </div>
</template>
