<script setup lang="ts">
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import ToolForm from '@/components/Garage/ToolForm.vue'
import type { Brand, ToolShelf } from '@/types/garage'

defineProps<{
  /** The tool shelves of the signed-in member, by position. */
  shelves: ToolShelf[]
  /** Every brand of the family, by name. */
  brands: Brand[]
}>()

const breadcrumb = [
  { label: 'Garage' },
  { label: 'Mes outils', to: route('garage.tools.index', undefined, false) },
  { label: 'Ajouter un outil' },
]
</script>

<template>
  <DashboardLayout
    title="Ajouter un outil"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-4xl">
      <ToolForm
        :tool="null"
        :shelves="shelves"
        :brands="brands"
      />
    </UCard>
  </DashboardLayout>
</template>
