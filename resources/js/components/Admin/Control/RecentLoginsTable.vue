<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import RelativeTime from './RelativeTime.vue'
import type { RecentLogin } from '@/types/admin'

defineProps<{
  logins: RecentLogin[]
}>()

const columns: TableColumn<RecentLogin>[] = [
  { accessorKey: 'name', header: 'Membre' },
  { accessorKey: 'lastLoginAt', header: 'Dernière connexion' },
]
</script>

<template>
  <UTable
    :data="logins"
    :columns="columns"
    empty="Aucune connexion enregistrée."
  >
    <template #lastLoginAt-cell="{ row }">
      <RelativeTime :value="row.original.lastLoginAt" />
    </template>
  </UTable>
</template>
