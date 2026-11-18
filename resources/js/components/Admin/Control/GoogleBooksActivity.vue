<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import RelativeTime from './RelativeTime.vue'
import type { GoogleBooksActivity, GoogleBooksFailure } from '@/types/admin'

defineProps<{
  activity: GoogleBooksActivity
}>()

const columns: TableColumn<GoogleBooksFailure>[] = [
  { accessorKey: 'createdAt', header: 'Date' },
  { accessorKey: 'statusLabel', header: 'Statut' },
  { accessorKey: 'type', header: 'Type' },
  { accessorKey: 'query', header: 'Requête' },
  { accessorKey: 'httpStatus', header: 'Code HTTP' },
  { accessorKey: 'member', header: 'Membre' },
]

const answeredStatuses = ['found', 'not_found']

function callsLabel(total: number): string {
  return total <= 1 ? `${total} appel` : `${total} appels`
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid gap-4 md:grid-cols-2">
      <UPageCard
        v-for="period in activity.periods"
        :key="period.key"
        :title="period.label"
        icon="i-lucide-book-search"
        variant="outline"
      >
        <p
          v-if="period.total === 0"
          class="text-muted"
        >
          Aucun appel
        </p>
        <div
          v-else
          class="space-y-2"
        >
          <p>
            <span class="text-2xl font-semibold">{{ callsLabel(period.total) }}</span>
            <span class="text-muted"> · {{ period.averageDurationMs }} ms en moyenne</span>
          </p>
          <div class="flex flex-wrap gap-2">
            <UBadge
              v-for="status in period.statuses"
              :key="status.status"
              :color="answeredStatuses.includes(status.status) ? 'success' : 'error'"
              variant="subtle"
            >
              {{ status.label }} : {{ status.count }}
            </UBadge>
          </div>
        </div>
      </UPageCard>
    </div>

    <UTable
      :data="activity.failures"
      :columns="columns"
      empty="Aucun échec récent."
    >
      <template #createdAt-cell="{ row }">
        <RelativeTime :value="row.original.createdAt" />
      </template>

      <template #statusLabel-cell="{ row }">
        <UBadge
          :label="row.original.statusLabel"
          color="error"
          variant="subtle"
        />
      </template>

      <template #query-cell="{ row }">
        <span class="break-all font-mono text-xs">{{ row.original.query }}</span>
      </template>

      <template #httpStatus-cell="{ row }">
        {{ row.original.httpStatus ?? '—' }}
      </template>

      <template #member-cell="{ row }">
        {{ row.original.member ?? '—' }}
      </template>
    </UTable>
  </div>
</template>
