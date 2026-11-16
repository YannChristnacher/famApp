<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'
import { route } from 'ziggy-js'
import AuthLayout from '@/layouts/AuthLayout.vue'

interface ForgotPasswordData {
  email: string
}

const page = usePage()
const form = useForm<ForgotPasswordData>({ email: '' })

const fields = computed<AuthFormField[]>(() => [
  {
    name: 'email',
    type: 'email',
    label: 'Adresse email',
    placeholder: 'vous@exemple.fr',
    autocomplete: 'username',
    required: true,
    error: form.errors.email,
  },
])

function submit(event: FormSubmitEvent<ForgotPasswordData>): void {
  form
    .transform(() => ({ email: event.data.email ?? '' }))
    .post(route('password.email', undefined, false))
}
</script>

<template>
  <AuthLayout title="Mot de passe oublié">
    <UAuthForm
      title="Mot de passe oublié"
      description="Indiquez votre adresse email : nous vous enverrons un lien pour choisir un nouveau mot de passe."
      icon="i-lucide-key-round"
      :fields="fields"
      :submit="{ label: 'Envoyer le lien' }"
      :loading="form.processing"
      @submit="submit"
    >
      <template #validation>
        <UAlert
          v-if="page.props.status"
          color="success"
          variant="subtle"
          icon="i-lucide-mail-check"
          :title="page.props.status"
        />
      </template>

      <template #footer>
        <ULink
          :to="route('login', undefined, false)"
          class="text-primary font-medium"
        >
          Retour à la connexion
        </ULink>
      </template>
    </UAuthForm>
  </AuthLayout>
</template>
