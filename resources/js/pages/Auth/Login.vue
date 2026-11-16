<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'
import { route } from 'ziggy-js'
import AuthLayout from '@/layouts/AuthLayout.vue'

interface LoginData {
  email: string
  password: string
  remember: boolean
}

const page = usePage()
const form = useForm<LoginData>({ email: '', password: '', remember: false })

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
  {
    name: 'password',
    type: 'password',
    label: 'Mot de passe',
    autocomplete: 'current-password',
    required: true,
    error: form.errors.password,
  },
  {
    name: 'remember',
    type: 'checkbox',
    label: 'Se souvenir de moi',
    defaultValue: false,
  },
])

function submit(event: FormSubmitEvent<LoginData>): void {
  form
    .transform(() => ({
      email: event.data.email ?? '',
      password: event.data.password ?? '',
      remember: event.data.remember ?? false,
    }))
    .post(route('login.store', undefined, false), {
      onFinish: () => form.reset('password'),
    })
}
</script>

<template>
  <AuthLayout title="Connexion">
    <UAuthForm
      title="Connexion"
      description="Accédez aux livres et aux outils de la famille."
      icon="i-lucide-lock"
      :fields="fields"
      :submit="{ label: 'Se connecter' }"
      :loading="form.processing"
      @submit="submit"
    >
      <template #password-hint>
        <ULink
          :to="route('password.request', undefined, false)"
          class="text-primary font-medium"
          tabindex="-1"
        >
          Mot de passe oublié ?
        </ULink>
      </template>

      <template #validation>
        <UAlert
          v-if="page.props.status"
          color="success"
          variant="subtle"
          icon="i-lucide-circle-check"
          :title="page.props.status"
        />
      </template>

      <template #footer>
        Pas encore de compte ?
        <ULink
          :to="route('account-request.create', undefined, false)"
          class="text-primary font-medium"
        >
          Demander un compte
        </ULink>
      </template>
    </UAuthForm>
  </AuthLayout>
</template>
