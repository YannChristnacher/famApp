<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

defineProps<{
  title: string
}>()
</script>

<template>
  <Head :title="title" />

  <main class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-4">
    <span class="text-2xl font-semibold text-highlighted">famApp</span>

    <UPageCard class="w-full max-w-md">
      <slot />
    </UPageCard>
  </main>
</template>
