<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import RelativeTime from './RelativeTime.vue'
import type { RecentAddition } from '@/types/admin'

defineProps<{
  additions: RecentAddition[]
}>()

const columns: TableColumn<RecentAddition>[] = [
  { accessorKey: 'type', header: 'Type' },
  { accessorKey: 'label', header: 'Titre ou nom' },
  { accessorKey: 'owner', header: 'Propriétaire' },
  { accessorKey: 'createdAt', header: 'Ajouté' },
]

const typeBadges: Record<RecentAddition['type'], { label: string, icon: string }> = {
  book: { label: 'Livre', icon: 'i-lucide-book' },
  tool: { label: 'Outil', icon: 'i-lucide-wrench' },
}
</script>

<template>
  <UTable
    :data="additions"
    :columns="columns"
    empty="Aucun livre ni outil ajouté pour le moment."
  >
    <template #type-cell="{ row }">
      <UBadge
        :label="typeBadges[row.original.type].label"
        :icon="typeBadges[row.original.type].icon"
        color="neutral"
        variant="subtle"
      />
    </template>

    <template #label-cell="{ row }">
      <ULink
        :to="row.original.url"
        class="font-medium text-highlighted hover:underline"
      >
        {{ row.original.label }}
      </ULink>
    </template>

    <template #createdAt-cell="{ row }">
      <RelativeTime :value="row.original.createdAt" />
    </template>
  </UTable>
</template>
