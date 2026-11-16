<script setup lang="ts">
import { route } from 'ziggy-js'
import AuthLayout from '@/layouts/AuthLayout.vue'

defineProps<{
  message: string
}>()
</script>

<template>
  <AuthLayout title="Lien inutilisable">
    <div class="space-y-6">
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-link-2-off"
        :title="message"
      />

      <div class="flex flex-col gap-2 sm:flex-row sm:justify-center">
        <UButton
          :to="route('password.request', undefined, false)"
          icon="i-lucide-key-round"
        >
          Mot de passe oublié
        </UButton>
        <UButton
          :to="route('login', undefined, false)"
          color="neutral"
          variant="outline"
        >
          Se connecter
        </UButton>
      </div>
    </div>
  </AuthLayout>
</template>
