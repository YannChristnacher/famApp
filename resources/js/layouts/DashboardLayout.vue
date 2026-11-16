<script setup lang="ts">
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import type { BreadcrumbItem } from '@nuxt/ui'
import { route } from 'ziggy-js'
import { filterEntriesByRoles, isActiveUrl, navigationRegistry, toNavigationMenuItems } from '@/navigation/registry'
import { useAuth } from '@/composables/useAuth'
import UserMenu from '@/components/Auth/UserMenu.vue'

defineProps<{
  title: string
  breadcrumb: BreadcrumbItem[]
}>()

const { roles } = useAuth()
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

    <UDashboardSearch
      :groups="[]"
      :color-mode="false"
    >
      <template #empty>
        <p class="py-6 text-center text-sm text-muted">
          La recherche dans les livres et les outils arrive bientôt.
        </p>
      </template>
    </UDashboardSearch>
  </UDashboardGroup>
</template>
