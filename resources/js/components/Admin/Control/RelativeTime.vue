<script setup lang="ts">
import { computed } from 'vue'
import { fullDate, relativeTime } from '@/support/admin/relativeTime'

const props = defineProps<{
  /** ISO 8601 date; « — » when null. */
  value: string | null
}>()

const relative = computed(() => (props.value === null ? null : relativeTime(props.value)))
const title = computed(() => (props.value === null ? undefined : fullDate(props.value)))
</script>

<template>
  <time
    v-if="value !== null"
    :datetime="value"
    :title="title"
    class="whitespace-nowrap"
  >{{ relative }}</time>
  <span v-else>—</span>
</template>
