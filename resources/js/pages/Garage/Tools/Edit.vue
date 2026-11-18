<script setup lang="ts">
import { computed } from 'vue'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import ToolForm from '@/components/Garage/ToolForm.vue'
import type { Brand, ToolDetails, ToolShelf } from '@/types/garage'

const props = defineProps<{
  tool: ToolDetails
  /** The tool shelves of the signed-in member, by position. */
  shelves: ToolShelf[]
  /** Every brand of the family, by name. */
  brands: Brand[]
}>()

const breadcrumb = computed(() => [
  { label: 'Garage' },
  { label: 'Mes outils', to: route('garage.tools.index', undefined, false) },
  { label: props.tool.name, to: route('garage.tools.show', { tool: props.tool.id }, false) },
  { label: 'Modifier' },
])
</script>

<template>
  <DashboardLayout
    :title="`Modifier « ${tool.name} »`"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-4xl">
      <ToolForm
        :key="tool.id"
        :tool="tool"
        :shelves="shelves"
        :brands="brands"
      />
    </UCard>
  </DashboardLayout>
</template>
