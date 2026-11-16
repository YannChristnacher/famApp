<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import type { AccountData, AccountRequestDraft } from '@/types/admin'
import type { Role } from '@/types/auth'

const props = defineProps<{
  accountRequest: AccountRequestDraft
  roles: Role[]
}>()

const roleLabels: Record<Role, string> = {
  user: 'Membre',
  admin: 'Administrateur',
}

const roleItems = props.roles.map((role) => ({ label: roleLabels[role], value: role }))

const breadcrumb = [
  { label: 'Administration' },
  { label: 'Demandes de compte', to: route('admin.account-requests.index', undefined, false) },
  { label: 'Créer le compte' },
]

const form = useForm<AccountData>({
  name: props.accountRequest.name,
  email: props.accountRequest.email,
  role: 'user',
})

function submit(): void {
  form.post(route('admin.account-requests.account.store', { accountRequest: props.accountRequest.id }, false))
}
</script>

<template>
  <DashboardLayout
    title="Créer le compte"
    :breadcrumb="breadcrumb"
  >
    <UCard class="mx-auto w-full max-w-xl">
      <template #header>
        <h2 class="text-lg font-semibold text-highlighted">
          Créer le compte de {{ accountRequest.name }}
        </h2>
        <p class="mt-1 text-sm text-muted">
          Le nouveau membre recevra un email pour définir son mot de passe.
        </p>
      </template>

      <UAlert
        v-if="accountRequest.message"
        title="Message du demandeur"
        :description="accountRequest.message"
        icon="i-lucide-message-square"
        color="neutral"
        variant="subtle"
        class="mb-6 whitespace-pre-line"
      />

      <UForm
        :state="form"
        class="space-y-4"
        @submit="submit"
      >
        <UFormField
          label="Nom"
          name="name"
          required
          :error="form.errors.name"
        >
          <UInput
            v-model="form.name"
            name="name"
            autocomplete="off"
            class="w-full"
          />
        </UFormField>

        <UFormField
          label="Adresse email"
          name="email"
          required
          :error="form.errors.email"
        >
          <UInput
            v-model="form.email"
            name="email"
            type="email"
            autocomplete="off"
            class="w-full"
          />
        </UFormField>

        <UFormField
          label="Rôle"
          name="role"
          required
          :error="form.errors.role"
        >
          <USelect
            v-model="form.role"
            :items="roleItems"
            name="role"
            class="w-full"
          />
        </UFormField>

        <div class="flex justify-end gap-2 pt-2">
          <UButton
            :to="route('admin.account-requests.index', undefined, false)"
            color="neutral"
            variant="ghost"
          >
            Annuler
          </UButton>
          <UButton
            type="submit"
            icon="i-lucide-user-plus"
            :loading="form.processing"
          >
            Créer le compte
          </UButton>
        </div>
      </UForm>
    </UCard>
  </DashboardLayout>
</template>
