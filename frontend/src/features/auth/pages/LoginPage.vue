<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { LoginCredentials } from '@/features/auth/types'

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
    const redirect =
      typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'home' }
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
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h1 class="text-center text-xl font-semibold text-gray-900">Sign in to your account</h1>

    <div v-if="resetSuccess" class="rounded-md bg-green-50 p-3 text-sm text-green-700">
      Password reset successful. Sign in with your new password.
    </div>

    <div v-if="serverError" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
      {{ serverError }}
    </div>

    <div>
      <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
      <input
        id="email"
        v-model="form.email"
        type="email"
        autocomplete="email"
        required
        :aria-describedby="errors.email ? 'email-error' : undefined"
        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        :class="{ 'border-red-500': errors.email }"
      />
      <p v-if="errors.email" id="email-error" class="mt-1 text-xs text-red-600">
        {{ errors.email[0] }}
      </p>
    </div>

    <div>
      <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
      <input
        id="password"
        v-model="form.password"
        type="password"
        autocomplete="current-password"
        required
        :aria-describedby="errors.password ? 'password-error' : undefined"
        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        :class="{ 'border-red-500': errors.password }"
      />
      <p v-if="errors.password" id="password-error" class="mt-1 text-xs text-red-600">
        {{ errors.password[0] }}
      </p>
    </div>

    <div class="flex items-center justify-between">
      <router-link
        :to="{ name: 'forgot-password' }"
        class="text-sm text-primary-600 hover:text-primary-500"
      >
        Forgot password?
      </router-link>
    </div>

    <button
      type="submit"
      :disabled="submitting"
      class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
      {{ submitting ? 'Signing in...' : 'Sign in' }}
    </button>

    <p class="text-center text-sm text-gray-600">
      Don't have an account?
      <router-link :to="{ name: 'register' }" class="text-primary-600 hover:text-primary-500">
        Register
      </router-link>
    </p>
  </form>
</template>
