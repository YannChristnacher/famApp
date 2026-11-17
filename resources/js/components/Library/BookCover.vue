<script setup lang="ts">
withDefaults(defineProps<{
  /** Public URL of the cover; a neutral placeholder is shown when null. */
  url: string | null
  title: string
  size?: 'card' | 'page'
}>(), { size: 'card' })
</script>

<template>
  <div
    class="aspect-[2/3] overflow-hidden rounded-md bg-elevated"
    :class="size === 'page' ? 'w-48 sm:w-56' : 'w-full'"
  >
    <img
      v-if="url"
      :src="url"
      :alt="`Couverture de ${title}`"
      class="size-full object-cover"
      loading="lazy"
    >
    <div
      v-else
      data-testid="cover-placeholder"
      role="img"
      :aria-label="`Pas de couverture pour ${title}`"
      class="flex size-full items-center justify-center text-dimmed"
    >
      <UIcon
        name="i-lucide-book"
        class="size-1/3"
      />
    </div>
  </div>
</template>
