<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import { route } from 'ziggy-js'
import type { AccountRequestRow, AccountRequestStatus } from '@/types/admin'

const props = withDefaults(defineProps<{
  accountRequests: AccountRequestRow[]
  /** Request whose rejection is being sent: every « Refuser » is blocked meanwhile. */
  rejectingId?: number | null
}>(), { rejectingId: null })

const emit = defineEmits<{
  reject: [accountRequest: AccountRequestRow]
}>()

const columns: TableColumn<AccountRequestRow>[] = [
  { accessorKey: 'name', header: 'Nom' },
  { accessorKey: 'email', header: 'Adresse email' },
  { accessorKey: 'message', header: 'Message' },
  { accessorKey: 'createdAt', header: 'Date' },
  { accessorKey: 'status', header: 'Statut' },
  { id: 'actions', header: 'Actions' },
]

const statusBadges: Record<AccountRequestStatus, { label: string, color: 'warning' | 'success' | 'neutral' }> = {
  pending: { label: 'En attente', color: 'warning' },
  approved: { label: 'Acceptée', color: 'success' },
  rejected: { label: 'Refusée', color: 'neutral' },
}

function reject(accountRequest: AccountRequestRow): void {
  if (props.rejectingId === null) {
    emit('reject', accountRequest)
  }
}

const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' })

function formatDate(isoDate: string | null): string {
  return isoDate === null ? '—' : dateFormat.format(new Date(isoDate))
}
</script>

<template>
  <UTable
    :data="accountRequests"
    :columns="columns"
    empty="Aucune demande de compte."
    :ui="{ td: 'align-top' }"
  >
    <template #message-cell="{ row }">
      <p class="max-w-md whitespace-pre-line break-words">
        {{ row.original.message || '—' }}
      </p>
    </template>

    <template #createdAt-cell="{ row }">
      <span class="whitespace-nowrap">{{ formatDate(row.original.createdAt) }}</span>
    </template>

    <template #status-cell="{ row }">
      <UBadge
        :color="statusBadges[row.original.status].color"
        variant="subtle"
      >
        {{ statusBadges[row.original.status].label }}
      </UBadge>
    </template>

    <template #actions-cell="{ row }">
      <div
        v-if="row.original.status === 'pending'"
        class="flex gap-2"
      >
        <UButton
          :to="route('admin.account-requests.account.create', { accountRequest: row.original.id }, false)"
          icon="i-lucide-user-plus"
          size="sm"
        >
          Créer le compte
        </UButton>
        <UButton
          icon="i-lucide-x"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="props.rejectingId === row.original.id"
          :disabled="props.rejectingId !== null"
          @click="reject(row.original)"
        >
          Refuser
        </UButton>
      </div>
    </template>
  </UTable>
</template>
