<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { CheckCircle, AlertCircle } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import type { LoginCredentials } from '@/features/auth/types'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const form = ref<LoginCredentials>({ email: '', password: '' })
const errors = ref<Record<string, string[]>>({})
const serverError = ref('')
const submitting = ref(false)
const resetSuccess = computed(() => route.query.reset === 'success')

async function handleSubmit(): Promise<void> {
  errors.value = {}
  serverError.value = ''
  submitting.value = true
  try {
    await auth.login(form.value)
    const rawRedirect = route.query.redirect
    const redirect =
      typeof rawRedirect === 'string' &&
      rawRedirect.startsWith('/') &&
      !rawRedirect.startsWith('//')
        ? rawRedirect
        : { name: 'home' }
    await router.push(redirect)
  } catch (e: unknown) {
    if (e && typeof e === 'object' && 'response' in e) {
      const error = e as {
        response?: {
          status?: number
          data?: { errors?: Record<string, string[]>; detail?: string }
        }
      }
      if (error.response?.data?.errors) {
        errors.value = error.response.data.errors
      } else if (error.response?.data?.detail) {
        serverError.value =
          error.response.status && error.response.status >= 500
            ? 'An unexpected error occurred. Please try again.'
            : error.response.data.detail
      }
    }
  } finally {
    submitting.value = false
  }
}

const fieldError = (field: string) => errors.value[field]?.[0]
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <div class="text-center">
      <h1 class="text-[var(--text-xl)] font-semibold tracking-tight text-[var(--text-primary)]">
        Sign in to your account
      </h1>
      <p class="mt-1 text-[var(--text-sm)] text-[var(--text-secondary)]">
        Welcome back to CareerPilot
      </p>
    </div>

    <div
      v-if="resetSuccess"
      class="flex items-center gap-2 rounded-[var(--radius-xl)] bg-[var(--color-success-50)] px-4 py-3 text-[var(--text-base)] text-[var(--color-success-700)]"
      style="box-shadow: var(--shadow-neo-inset)"
    >
      <CheckCircle class="size-4 shrink-0 text-[var(--color-success-500)]" aria-hidden="true" />
      Password reset successful. Sign in with your new password.
    </div>

    <div
      v-if="serverError"
      class="flex items-center gap-2 rounded-[var(--radius-xl)] bg-[var(--color-error-50)] px-4 py-3 text-[var(--text-base)] text-[var(--color-error-700)]"
      style="box-shadow: var(--shadow-neo-inset)"
    >
      <AlertCircle class="size-4 shrink-0 text-[var(--color-error-500)]" aria-hidden="true" />
      {{ serverError }}
    </div>

    <FormField label="Email" :error="fieldError('email')" required>
      <Input
        v-model="form.email"
        name="email"
        type="email"
        autocomplete="email"
        placeholder="you@example.com"
        required
      />
    </FormField>

    <FormField label="Password" :error="fieldError('password')" required>
      <Input
        v-model="form.password"
        name="password"
        type="password"
        autocomplete="current-password"
        required
      />
    </FormField>

    <div class="flex items-center justify-end">
      <RouterLink
        :to="{ name: 'forgot-password' }"
        class="text-[var(--text-sm)] font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
      >
        Forgot password?
      </RouterLink>
    </div>

    <Button type="submit" :loading="submitting" :disabled="submitting" class="w-full">
      Sign in
    </Button>

    <p class="text-center text-[var(--text-base)] text-[var(--text-secondary)]">
      Don't have an account?
      <RouterLink
        :to="{ name: 'register' }"
        class="font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
      >
        Register
      </RouterLink>
    </p>
  </form>
</template>
