<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertCircle } from '@lucide/vue'
import { resetPassword, fetchCsrfCookie } from '@/features/auth/api'
import { extractProblemDetail } from '@/api/client/problem-detail'
import BackButton from '@/components/ui/BackButton.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'

const route = useRoute()
const router = useRouter()

const form = ref({
  email: (route.query.email as string) || '',
  token: (route.query.token as string) || '',
  password: '',
  password_confirmation: '',
})
const errors = ref<Record<string, string[]>>({})
const serverError = ref('')
const submitting = ref(false)

async function handleSubmit(): Promise<void> {
  errors.value = {}
  serverError.value = ''
  submitting.value = true
  try {
    await fetchCsrfCookie()
    await resetPassword(form.value)
    await router.push({ name: 'login', query: { reset: 'success' } })
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
        Set new password
      </h1>
      <p class="mt-1 text-[var(--text-base)] text-[var(--text-secondary)]">
        Enter your new password below.
      </p>
    </div>

    <input type="hidden" name="email" :value="form.email" />
    <input type="hidden" name="token" :value="form.token" />

    <div
      v-if="serverError"
      class="flex items-center gap-2 rounded-[var(--radius-xl)] bg-[var(--color-error-50)] px-4 py-3 text-[var(--text-base)] text-[var(--color-error-700)]"
      style="box-shadow: var(--shadow-neo-inset)"
      role="alert"
    >
      <AlertCircle class="size-4 shrink-0 text-[var(--color-error-500)]" aria-hidden="true" />
      {{ serverError }}
    </div>

    <FormField label="New password" :error="errors.password?.[0]" required>
      <Input
        v-model="form.password"
        name="password"
        type="password"
        autocomplete="new-password"
        required
      />
    </FormField>

    <FormField label="Confirm new password" :error="errors.password_confirmation?.[0]" required>
      <Input
        v-model="form.password_confirmation"
        name="password_confirmation"
        type="password"
        autocomplete="new-password"
        required
      />
    </FormField>

    <Button type="submit" :loading="submitting" :disabled="submitting" class="w-full">
      Reset password
    </Button>
  </form>
</template>
