<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import AccountRequestsTable from '@/components/Admin/AccountRequestsTable.vue'
import type { AccountRequestRow } from '@/types/admin'

defineProps<{
  accountRequests: AccountRequestRow[]
}>()

const breadcrumb = [{ label: 'Administration' }, { label: 'Demandes de compte' }]

const rejectingId = ref<number | null>(null)

function reject(accountRequest: AccountRequestRow): void {
  if (rejectingId.value !== null) {
    return
  }

  router.post(
    route('admin.account-requests.reject', { accountRequest: accountRequest.id }, false),
    {},
    {
      preserveScroll: true,
      onStart: () => { rejectingId.value = accountRequest.id },
      onFinish: () => { rejectingId.value = null },
    },
  )
}
</script>

<template>
  <DashboardLayout
    title="Demandes de compte"
    :breadcrumb="breadcrumb"
  >
    <AccountRequestsTable
      :account-requests="accountRequests"
      :rejecting-id="rejectingId"
      @reject="reject"
    />
  </DashboardLayout>
</template>
