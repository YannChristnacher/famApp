<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'
import { route } from 'ziggy-js'
import AuthLayout from '@/layouts/AuthLayout.vue'

interface AccountRequestData {
  name: string
  email: string
  message: string
}

const MESSAGE_MAX_LENGTH = 2000

const form = useForm<AccountRequestData>({ name: '', email: '', message: '' })

const fields = computed<AuthFormField[]>(() => [
  {
    name: 'name',
    type: 'text',
    label: 'Nom',
    placeholder: 'Prénom et nom',
    autocomplete: 'name',
    required: true,
    error: form.errors.name,
  },
  {
    name: 'email',
    type: 'email',
    label: 'Adresse email',
    placeholder: 'vous@exemple.fr',
    autocomplete: 'email',
    required: true,
    error: form.errors.email,
  },
  {
    name: 'message',
    type: 'text',
    label: 'Message',
    description: 'Facultatif : dites-nous qui vous êtes dans la famille.',
    error: form.errors.message,
  },
])

type ContactData = Pick<AccountRequestData, 'name' | 'email'>

// UAuthForm holds the name and the email; the message textarea, rendered
// through the field slot, is bound to the Inertia form directly.
function submit(event: FormSubmitEvent<Partial<ContactData>>): void {
  form
    .transform((data) => ({
      name: event.data.name ?? '',
      email: event.data.email ?? '',
      message: data.message,
    }))
    .post(route('account-request.store', undefined, false))
}
</script>

<template>
  <AuthLayout title="Demander un compte">
    <UAuthForm
      title="Demander un compte"
      description="Un administrateur étudiera votre demande et vous contactera par email."
      icon="i-lucide-user-plus"
      :fields="fields"
      :submit="{ label: 'Envoyer la demande' }"
      :loading="form.processing"
      @submit="submit"
    >
      <template #message-field>
        <UTextarea
          v-model="form.message"
          name="message"
          :rows="4"
          :maxlength="MESSAGE_MAX_LENGTH"
          autoresize
          class="w-full"
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
