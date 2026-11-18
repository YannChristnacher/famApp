<script setup lang="ts">
import { route } from 'ziggy-js'
import ToolThumbnail from '@/components/Garage/ToolThumbnail.vue'
import type { Tool } from '@/types/garage'

defineProps<{
  tool: Tool
}>()
</script>

<template>
  <ULink
    :to="route('garage.tools.show', { tool: tool.id }, false)"
    raw
    data-testid="tool"
    class="group block rounded-lg focus-visible:outline-2 focus-visible:outline-primary"
  >
    <ToolThumbnail
      :url="tool.thumbnail_url"
      :name="tool.name"
    />
    <p class="mt-2 line-clamp-2 font-medium text-highlighted group-hover:underline">
      {{ tool.name }}
    </p>
    <p
      v-if="tool.brand"
      class="truncate text-sm text-muted"
      data-testid="tool-brand"
    >
      {{ tool.brand.name }}
    </p>
  </ULink>
</template>
