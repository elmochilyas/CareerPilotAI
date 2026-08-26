<script setup lang="ts">
import { ref } from 'vue'
import { AlertCircle, CheckCircle } from '@lucide/vue'
import { sendForgotPasswordLink, fetchCsrfCookie } from '@/features/auth/api'
import { extractProblemDetail } from '@/api/client/problem-detail'
import BackButton from '@/components/ui/BackButton.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'

const email = ref('')
const errors = ref<Record<string, string[]>>({})
const serverError = ref('')
const successMessage = ref('')
const submitting = ref(false)

async function handleSubmit(): Promise<void> {
  errors.value = {}
  serverError.value = ''
  successMessage.value = ''
  submitting.value = true
  try {
    await fetchCsrfCookie()
    await sendForgotPasswordLink({ email: email.value })
    successMessage.value = 'If that email exists, we have sent a password reset link.'
  } catch (e: unknown) {
    const detail = extractProblemDetail(e as never)
    if (detail?.errors && typeof detail.errors === 'object') {
      errors.value = detail.errors as Record<string, string[]>
    } else if (detail) {
      serverError.value =
        detail.status >= 500 ? 'An unexpected error occurred. Please try again.' : detail.detail
    } else if (e && typeof e === 'object' && 'response' in e) {
      const error = e as { response?: { data?: { errors?: Record<string, string[]> } } }
      if (error.response?.data?.errors) {
        errors.value = error.response.data.errors
      } else {
        serverError.value = 'An unexpected error occurred. Please try again.'
      }
    } else {
      serverError.value = 'An unexpected error occurred. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <BackButton :to="{ name: 'login' }" label="Back to sign in" class="mb-4" />

    <div class="text-center">
      <h1 class="text-[var(--text-xl)] font-semibold tracking-tight text-[var(--text-primary)]">
        Reset your password
      </h1>
      <p class="mt-1 text-[var(--text-base)] text-[var(--text-secondary)]">
        Enter your email address and we'll send you a link to reset your password.
      </p>
    </div>

    <div
      v-if="successMessage"
      class="flex items-center gap-2 rounded-[var(--radius-xl)] bg-[var(--color-success-50)] px-4 py-3 text-[var(--text-base)] text-[var(--color-success-700)]"
      style="box-shadow: var(--shadow-neo-inset)"
    >
      <CheckCircle class="size-4 shrink-0 text-[var(--color-success-500)]" aria-hidden="true" />
      {{ successMessage }}
    </div>

    <div
      v-if="serverError"
      class="flex items-center gap-2 rounded-[var(--radius-xl)] bg-[var(--color-error-50)] px-4 py-3 text-[var(--text-base)] text-[var(--color-error-700)]"
      style="box-shadow: var(--shadow-neo-inset)"
      role="alert"
    >
      <AlertCircle class="size-4 shrink-0 text-[var(--color-error-500)]" aria-hidden="true" />
      {{ serverError }}
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
  </form>
</template>
