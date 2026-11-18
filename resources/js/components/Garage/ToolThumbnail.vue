<script setup lang="ts">
withDefaults(defineProps<{
  /** Public URL of the first photo; a neutral placeholder is shown when null. */
  url: string | null
  name: string
}>(), {})
</script>

<template>
  <div class="aspect-square w-full overflow-hidden rounded-md bg-elevated">
    <img
      v-if="url"
      :src="url"
      :alt="`Photo de ${name}`"
      class="size-full object-cover"
      loading="lazy"
    >
    <div
      v-else
      data-testid="thumbnail-placeholder"
      role="img"
      :aria-label="`Pas de photo pour ${name}`"
      class="flex size-full items-center justify-center text-dimmed"
    >
      <UIcon
        name="i-lucide-wrench"
        class="size-1/3"
      />
    </div>
  </div>
</template>
