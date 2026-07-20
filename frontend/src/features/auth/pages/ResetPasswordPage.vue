<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { resetPassword } from '@/features/auth/api'
import { fetchCsrfCookie } from '@/features/auth/api'

const route = useRoute()
const router = useRouter()

const form = ref({
  email: (route.query.email as string) || '',
  token: (route.query.token as string) || '',
  password: '',
  password_confirmation: '',
})
const errors = ref<Record<string, string[]>>({})
const submitting = ref(false)

async function handleSubmit(): Promise<void> {
  errors.value = {}
  submitting.value = true

  try {
    await fetchCsrfCookie()
    await resetPassword(form.value)
    await router.push({ name: 'login', query: { reset: 'success' } })
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
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h1 class="text-center text-xl font-semibold text-gray-900">Set new password</h1>

    <input type="hidden" name="email" :value="form.email" />
    <input type="hidden" name="token" :value="form.token" />

    <div>
      <label for="password" class="block text-sm font-medium text-gray-700">New password</label>
      <input
        id="password"
        v-model="form.password"
        type="password"
        autocomplete="new-password"
        required
        minlength="8"
        :aria-describedby="errors.password ? 'password-error' : undefined"
        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        :class="{ 'border-red-500': errors.password }"
      />
      <p v-if="errors.password" id="password-error" class="mt-1 text-xs text-red-600">
        {{ errors.password[0] }}
      </p>
    </div>

    <div>
      <label for="password_confirmation" class="block text-sm font-medium text-gray-700"
        >Confirm new password</label
      >
      <input
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        autocomplete="new-password"
        required
        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        :class="{ 'border-red-500': errors.password }"
      />
    </div>

    <button
      type="submit"
      :disabled="submitting"
      class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
      {{ submitting ? 'Resetting...' : 'Reset password' }}
    </button>

    <p class="text-center text-sm text-gray-600">
      <router-link :to="{ name: 'login' }" class="text-primary-600 hover:text-primary-500">
        Back to sign in
      </router-link>
    </p>
  </form>
</template>
