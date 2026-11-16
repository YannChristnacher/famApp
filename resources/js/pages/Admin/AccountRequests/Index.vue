<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import AccountRequestsTable from '@/components/Admin/AccountRequestsTable.vue'
import type { AccountRequestRow } from '@/types/admin'

defineProps<{
  accountRequests: AccountRequestRow[]
}>()

const breadcrumb = [{ label: 'Administration' }, { label: 'Demandes de compte' }]

function reject(accountRequest: AccountRequestRow): void {
  router.post(
    route('admin.account-requests.reject', { accountRequest: accountRequest.id }, false),
    {},
    { preserveScroll: true },
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
      @reject="reject"
    />
  </DashboardLayout>
</template>
