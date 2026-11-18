<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import ControlCounters from '@/components/Admin/Control/ControlCounters.vue'
import ErrorLogTable from '@/components/Admin/Control/ErrorLogTable.vue'
import FailedJobsTable from '@/components/Admin/Control/FailedJobsTable.vue'
import GoogleBooksActivity from '@/components/Admin/Control/GoogleBooksActivity.vue'
import RecentAdditionsTable from '@/components/Admin/Control/RecentAdditionsTable.vue'
import RecentLoginsTable from '@/components/Admin/Control/RecentLoginsTable.vue'
import type {
  ControlCounts,
  ErrorLogEntry,
  FailedJobs,
  GoogleBooksActivity as GoogleBooksActivityData,
  RecentAddition,
  RecentLogin,
} from '@/types/admin'

defineProps<{
  pendingAccountRequests: number
  counts: ControlCounts
  recentAdditions: RecentAddition[]
  recentLogins: RecentLogin[]
  failedJobs: FailedJobs
  errors: ErrorLogEntry[]
  googleBooks: GoogleBooksActivityData
}>()

const breadcrumb = [{ label: 'Administration' }, { label: 'Contrôle' }]

function failedJobsLabel(total: number): string {
  return total <= 1 ? `${total} job en échec` : `${total} jobs en échec`
}
</script>

<template>
  <DashboardLayout
    title="Contrôle"
    :breadcrumb="breadcrumb"
  >
    <div class="space-y-6">
      <ControlCounters
        :pending-account-requests="pendingAccountRequests"
        :counts="counts"
      />

      <div class="grid gap-6 xl:grid-cols-3">
        <UCard
          data-testid="control-recent-additions"
          class="xl:col-span-2"
        >
          <template #header>
            <h2 class="font-semibold">
              Derniers ajouts
            </h2>
          </template>
          <RecentAdditionsTable :additions="recentAdditions" />
        </UCard>

        <UCard data-testid="control-recent-logins">
          <template #header>
            <h2 class="font-semibold">
              Dernières connexions
            </h2>
          </template>
          <RecentLoginsTable :logins="recentLogins" />
        </UCard>
      </div>

      <UCard data-testid="control-failed-jobs">
        <template #header>
          <div class="flex items-center justify-between gap-2">
            <h2 class="font-semibold">
              Jobs en échec
            </h2>
            <UBadge
              :label="failedJobsLabel(failedJobs.total)"
              :color="failedJobs.total > 0 ? 'error' : 'success'"
              variant="subtle"
            />
          </div>
        </template>
        <FailedJobsTable :failed-jobs="failedJobs.latest" />
      </UCard>

      <UCard data-testid="control-errors">
        <template #header>
          <h2 class="font-semibold">
            Dernières erreurs
          </h2>
          <p class="text-sm text-muted">
            Entrées de niveau « error » ou plus graves du journal Laravel, sans contexte ni trace, secrets masqués.
          </p>
        </template>
        <ErrorLogTable :errors="errors" />
      </UCard>

      <UCard data-testid="control-google-books">
        <template #header>
          <h2 class="font-semibold">
            Appels Google Books
          </h2>
        </template>
        <GoogleBooksActivity :activity="googleBooks" />
      </UCard>
    </div>
  </DashboardLayout>
</template>
