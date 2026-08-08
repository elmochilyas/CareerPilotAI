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
        response?: { data?: { errors?: Record<string, string[]>; detail?: string } }
      }
      if (error.response?.data?.errors) {
        errors.value = error.response.data.errors
      } else if (error.response?.data?.detail) {
        serverError.value = error.response.data.detail
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
      <h1 class="text-lg font-bold tracking-tight text-slate-900">Sign in to your account</h1>
      <p class="mt-1 text-sm text-slate-500">Welcome back to CareerPilot</p>
    </div>

    <div
      v-if="resetSuccess"
      class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
    >
      <CheckCircle class="size-4 shrink-0 text-emerald-500" aria-hidden="true" />
      Password reset successful. Sign in with your new password.
    </div>

    <div
      v-if="serverError"
      class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      <AlertCircle class="size-4 shrink-0 text-red-500" aria-hidden="true" />
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
        class="text-sm font-medium text-primary-600 hover:text-primary-500"
      >
        Forgot password?
      </RouterLink>
    </div>

    <Button type="submit" :loading="submitting" :disabled="submitting" class="w-full">
      Sign in
    </Button>

    <p class="text-center text-sm text-slate-500">
      Don't have an account?
      <RouterLink
        :to="{ name: 'register' }"
        class="font-medium text-primary-600 hover:text-primary-500"
      >
        Register
      </RouterLink>
    </p>
  </form>
</template>
