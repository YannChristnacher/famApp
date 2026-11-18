<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import RelativeTime from './RelativeTime.vue'
import type { ErrorLogEntry, ErrorLogLevel } from '@/types/admin'

defineProps<{
  errors: ErrorLogEntry[]
}>()

const columns: TableColumn<ErrorLogEntry>[] = [
  { accessorKey: 'loggedAt', header: 'Date' },
  { accessorKey: 'level', header: 'Niveau' },
  { accessorKey: 'message', header: 'Message' },
]

const levelVariants: Record<ErrorLogLevel, 'subtle' | 'solid'> = {
  error: 'subtle',
  critical: 'solid',
  alert: 'solid',
  emergency: 'solid',
}
</script>

<template>
  <UTable
    :data="errors"
    :columns="columns"
    empty="Aucune erreur récente dans le journal."
    :ui="{ td: 'align-top' }"
  >
    <template #loggedAt-cell="{ row }">
      <RelativeTime :value="row.original.loggedAt" />
    </template>

    <template #level-cell="{ row }">
      <UBadge
        :label="row.original.level.toUpperCase()"
        color="error"
        :variant="levelVariants[row.original.level]"
      />
    </template>

    <template #message-cell="{ row }">
      <p class="max-w-2xl whitespace-pre-wrap break-words font-mono text-xs">
        {{ row.original.message }}
      </p>
    </template>
  </UTable>
</template>
