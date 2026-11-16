<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue'

defineProps<{
  appName: string
}>()

const breadcrumb = [{ label: 'Accueil' }]
</script>

<template>
  <DashboardLayout
    title="Accueil"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-2xl">
      <h2 class="text-xl font-semibold text-highlighted">
        Bienvenue sur {{ appName }}
      </h2>
      <p class="mt-2 text-muted">
        Les livres et les outils de la famille, au même endroit.
      </p>
    </UCard>
  </DashboardLayout>
</template>
