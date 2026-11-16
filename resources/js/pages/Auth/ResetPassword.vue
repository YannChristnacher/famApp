<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'
import { route } from 'ziggy-js'
import AuthLayout from '@/layouts/AuthLayout.vue'

const props = defineProps<{
  token: string
  email: string
}>()

interface ResetPasswordData {
  email: string
  password: string
  password_confirmation: string
}

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' })

const fields = computed<AuthFormField[]>(() => [
  {
    name: 'email',
    type: 'email',
    label: 'Adresse email',
    autocomplete: 'username',
    required: true,
    defaultValue: props.email,
    error: form.errors.email,
  },
  {
    name: 'password',
    type: 'password',
    label: 'Nouveau mot de passe',
    autocomplete: 'new-password',
    required: true,
    error: form.errors.password,
  },
  {
    name: 'password_confirmation',
    type: 'password',
    label: 'Confirmation du mot de passe',
    autocomplete: 'new-password',
    required: true,
  },
])

function submit(event: FormSubmitEvent<ResetPasswordData>): void {
  form
    .transform(() => ({
      token: props.token,
      email: event.data.email ?? '',
      password: event.data.password ?? '',
      password_confirmation: event.data.password_confirmation ?? '',
    }))
    .post(route('password.update', undefined, false), {
      onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
  <AuthLayout title="Nouveau mot de passe">
    <UAuthForm
      title="Nouveau mot de passe"
      description="Choisissez le nouveau mot de passe de votre compte famApp."
      icon="i-lucide-key-round"
      :fields="fields"
      :submit="{ label: 'Enregistrer le mot de passe' }"
      :loading="form.processing"
      @submit="submit"
    />
  </AuthLayout>
</template>
