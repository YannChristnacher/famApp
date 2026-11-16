<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'
import AuthLayout from '@/layouts/AuthLayout.vue'

const props = defineProps<{
  name: string
  email: string
  /** Signed invitation url the form posts back to. */
  submitUrl: string
}>()

interface SetPasswordData {
  password: string
  password_confirmation: string
}

const form = useForm<SetPasswordData>({ password: '', password_confirmation: '' })

const fields = computed<AuthFormField[]>(() => [
  {
    name: 'password',
    type: 'password',
    label: 'Mot de passe',
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

function submit(event: FormSubmitEvent<Partial<SetPasswordData>>): void {
  form
    .transform(() => ({
      password: event.data.password ?? '',
      password_confirmation: event.data.password_confirmation ?? '',
    }))
    .post(props.submitUrl, {
      onFinish: () => form.reset(),
    })
}
</script>

<template>
  <AuthLayout title="Définir mon mot de passe">
    <UAuthForm
      :title="`Bienvenue ${name}`"
      :description="`Choisissez le mot de passe de votre compte famApp (${email}).`"
      icon="i-lucide-key-round"
      :fields="fields"
      :submit="{ label: 'Enregistrer mon mot de passe' }"
      :loading="form.processing"
      @submit="submit"
    />
  </AuthLayout>
</template>
