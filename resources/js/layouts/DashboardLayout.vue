<script setup lang="ts">
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import type { BreadcrumbItem } from '@nuxt/ui'
import { route } from 'ziggy-js'
import { filterEntriesByRoles, isActiveUrl, navigationRegistry, toNavigationMenuItems } from '@/navigation/registry'
import { useAuth } from '@/composables/useAuth'
import { useFlashToasts } from '@/composables/useFlashToasts'
import UserMenu from '@/components/Auth/UserMenu.vue'
import GlobalSearch from '@/components/Core/GlobalSearch.vue'

defineProps<{
  title: string
  breadcrumb: BreadcrumbItem[]
}>()

const { roles } = useAuth()
useFlashToasts()
const page = usePage()
const relativeUrl = (name: string) => route(name, undefined, false)

const navigationItems = computed(() =>
  toNavigationMenuItems(filterEntriesByRoles(navigationRegistry, roles.value), {
    url: relativeUrl,
    isActive: (name) => isActiveUrl(page.url, relativeUrl(name)),
  }),
)
</script>

<template>
  <Head :title="title" />

  <UDashboardGroup>
    <UDashboardSidebar
      collapsible
      resizable
    >
      <template #header="{ collapsed }">
        <span
          class="truncate text-lg font-semibold text-highlighted"
        >
          {{ collapsed ? 'fA' : 'famApp' }}
        </span>
      </template>

      <template #default="{ collapsed }">
        <UNavigationMenu
          :items="navigationItems"
          :collapsed="collapsed"
          orientation="vertical"
          tooltip
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <UDashboardPanel>
      <template #header>
        <UDashboardNavbar :title="title">
          <template #leading>
            <UDashboardSidebarCollapse />
          </template>

          <template #right>
            <UDashboardSearchButton />
          </template>
        </UDashboardNavbar>

        <UDashboardToolbar>
          <UBreadcrumb :items="breadcrumb" />
        </UDashboardToolbar>
      </template>

      <template #body>
        <slot />
      </template>
    </UDashboardPanel>

    <GlobalSearch />
  </UDashboardGroup>
</template>
