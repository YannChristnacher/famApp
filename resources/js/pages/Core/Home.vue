<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

defineProps<{
  appName: string
}>()
</script>

<template>
  <UApp>
    <Head title="Accueil" />
    <main class="flex min-h-screen items-center justify-center p-6">
      <UCard class="w-full max-w-md text-center">
        <h1 class="text-2xl font-semibold">
          {{ appName }}
        </h1>
        <p class="mt-2 text-muted">
          Les livres et les outils de la famille, au même endroit.
        </p>
      </UCard>
    </main>
  </UApp>
</template>
