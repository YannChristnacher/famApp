<script setup lang="ts">
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { inventoryLabel } from '@/support/family/counts'
import type { FamilyMember } from '@/types/family'

defineProps<{
  /** Every member, by name, with the numbers of their books and tools. */
  members: FamilyMember[]
}>()

const breadcrumb = [{ label: 'La famille' }]
</script>

<template>
  <DashboardLayout
    title="La famille"
    :breadcrumb="breadcrumb"
  >
    <div class="mx-auto w-full max-w-3xl space-y-4">
      <p class="text-muted">
        Parcourez les livres et les outils de chaque membre de la famille.
      </p>

      <UEmpty
        v-if="members.length === 0"
        icon="i-lucide-users"
        title="Aucun membre pour le moment."
        variant="naked"
      />

      <UPageList
        v-else
        divide
        class="rounded-lg border border-default"
      >
        <div
          v-for="member in members"
          :key="member.id"
          data-testid="family-member"
          class="flex items-center justify-between gap-3 px-4 py-3"
        >
          <UUser
            :name="member.name"
            :description="inventoryLabel(member.books_count, member.tools_count)"
            :avatar="{ alt: member.name, icon: 'i-lucide-user' }"
            :to="route('family.members.show', { member: member.id }, false)"
          />
          <UBadge
            v-if="member.own"
            label="Vous"
            color="primary"
            variant="subtle"
            data-testid="family-member-own"
          />
        </div>
      </UPageList>
    </div>
  </DashboardLayout>
</template>
