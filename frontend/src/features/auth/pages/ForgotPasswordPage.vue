<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { CheckCircle } from '@lucide/vue'
import { sendForgotPasswordLink, fetchCsrfCookie } from '@/features/auth/api'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'

const email = ref('')
const errors = ref<Record<string, string[]>>({})
const successMessage = ref('')
const submitting = ref(false)

async function handleSubmit(): Promise<void> {
  errors.value = {}
  successMessage.value = ''
  submitting.value = true
  try {
    await fetchCsrfCookie()
    await sendForgotPasswordLink({ email: email.value })
    successMessage.value = 'If that email exists, we have sent a password reset link.'
  } catch (e: unknown) {
    if (e && typeof e === 'object' && 'response' in e) {
      const error = e as { response?: { data?: { errors?: Record<string, string[]> } } }
      if (error.response?.data?.errors) {
        errors.value = error.response.data.errors
      }
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <div class="text-center">
      <h1 class="text-lg font-bold tracking-tight text-slate-900">Reset your password</h1>
      <p class="mt-1 text-sm text-slate-500">
        Enter your email address and we'll send you a link to reset your password.
      </p>
    </div>

    <div
      v-if="successMessage"
      class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
    >
      <CheckCircle class="size-4 shrink-0 text-emerald-500" aria-hidden="true" />
      {{ successMessage }}
    </div>

    <FormField label="Email" :error="errors.email?.[0]" required>
      <Input
        v-model="email"
        name="email"
        type="email"
        autocomplete="email"
        placeholder="you@example.com"
        required
      />
    </FormField>

    <Button type="submit" :loading="submitting" :disabled="submitting" class="w-full">
      Send reset link
    </Button>

    <p class="text-center text-sm text-slate-500">
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-primary-600 hover:text-primary-500"
      >
        Back to sign in
      </RouterLink>
    </p>
  </form>
</template>
