<script setup lang="ts">
import { ref } from 'vue'
import type { TableColumn } from '@nuxt/ui'
import FailedJobActionModal from './FailedJobActionModal.vue'
import RelativeTime from './RelativeTime.vue'
import type { FailedJob, FailedJobAction } from '@/types/admin'

defineProps<{
  failedJobs: FailedJob[]
}>()

const columns: TableColumn<FailedJob>[] = [
  { accessorKey: 'failedAt', header: 'Échec' },
  { accessorKey: 'job', header: 'Job' },
  { accessorKey: 'queue', header: 'File' },
  { accessorKey: 'exception', header: 'Exception' },
  { id: 'actions', header: 'Actions' },
]

const selectedJob = ref<FailedJob | null>(null)
const selectedAction = ref<FailedJobAction>('retry')
const confirming = ref(false)

function ask(job: FailedJob, action: FailedJobAction): void {
  selectedJob.value = job
  selectedAction.value = action
  confirming.value = true
}

/** Short class name; the complete one stays in the tooltip. */
function shortName(className: string): string {
  return className.split('\\').pop() ?? className
}
</script>

<template>
  <UTable
    :data="failedJobs"
    :columns="columns"
    empty="Aucun job en échec."
    :ui="{ td: 'align-top' }"
  >
    <template #failedAt-cell="{ row }">
      <RelativeTime :value="row.original.failedAt" />
    </template>

    <template #job-cell="{ row }">
      <span
        :title="row.original.job"
        class="font-medium"
      >{{ shortName(row.original.job) }}</span>
    </template>

    <template #queue-cell="{ row }">
      <UBadge
        :label="row.original.queue"
        color="neutral"
        variant="outline"
      />
    </template>

    <template #exception-cell="{ row }">
      <p class="max-w-md whitespace-pre-wrap break-words font-mono text-xs">
        {{ row.original.exception }}
      </p>
    </template>

    <template #actions-cell="{ row }">
      <div class="flex gap-2">
        <UButton
          icon="i-lucide-rotate-ccw"
          size="sm"
          variant="outline"
          @click="ask(row.original, 'retry')"
        >
          Relancer
        </UButton>
        <UButton
          icon="i-lucide-trash-2"
          size="sm"
          color="error"
          variant="ghost"
          @click="ask(row.original, 'destroy')"
        >
          Supprimer
        </UButton>
      </div>
    </template>
  </UTable>

  <FailedJobActionModal
    v-model:open="confirming"
    :job="selectedJob"
    :action="selectedAction"
  />
</template>
