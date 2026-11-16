<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { DropdownMenuItem } from '@nuxt/ui'
import { route } from 'ziggy-js'
import { useAuth } from '@/composables/useAuth'

defineProps<{
  collapsed?: boolean
}>()

const { user } = useAuth()

function logout(): void {
  router.post(route('logout', undefined, false))
}

const items = computed<DropdownMenuItem[][]>(() => [
  [{ type: 'label', label: user.value?.email }],
  [{ label: 'Se déconnecter', icon: 'i-lucide-log-out', onSelect: logout }],
])
</script>

<template>
  <UDropdownMenu
    v-if="user"
    :items="items"
    :content="{ align: 'center', collisionPadding: 12 }"
    :ui="{ content: 'w-60' }"
  >
    <UButton
      :label="collapsed ? undefined : user.name"
      :avatar="{ alt: user.name }"
      :trailing-icon="collapsed ? undefined : 'i-lucide-chevrons-up-down'"
      :square="collapsed"
      :aria-label="`Menu de ${user.name}`"
      color="neutral"
      variant="ghost"
      block
      class="data-[state=open]:bg-elevated"
      :ui="{ trailingIcon: 'text-dimmed' }"
    />
  </UDropdownMenu>
</template>
