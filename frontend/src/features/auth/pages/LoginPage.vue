<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { LoginCredentials } from '@/features/auth/types'
import Button from '@/components/ui/Button.vue'
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
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h1 class="text-center text-lg font-semibold text-slate-900">Sign in to your account</h1>

    <div
      v-if="resetSuccess"
      class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"
    >
      Password reset successful. Sign in with your new password.
    </div>

    <div
      v-if="serverError"
      class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    >
      {{ serverError }}
    </div>

    <Input
      v-model="form.email"
      name="email"
      label="Email"
      type="email"
      autocomplete="email"
      required
      :error="fieldError('email')"
    />

    <Input
      v-model="form.password"
      name="password"
      label="Password"
      type="password"
      autocomplete="current-password"
      required
      :error="fieldError('password')"
    />

    <div class="flex items-center justify-between">
      <RouterLink
        :to="{ name: 'forgot-password' }"
        class="text-sm text-primary-600 hover:text-primary-500"
      >
        Forgot password?
      </RouterLink>
    </div>

    <Button type="submit" :disabled="submitting" class="w-full">
      {{ submitting ? 'Signing in...' : 'Sign in' }}
    </Button>

    <p class="text-center text-sm text-slate-600">
      Don't have an account?
      <RouterLink :to="{ name: 'register' }" class="text-primary-600 hover:text-primary-500">
        Register
      </RouterLink>
    </p>
  </form>
</template>
