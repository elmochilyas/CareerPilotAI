<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { sendForgotPasswordLink } from '@/features/auth/api'
import { fetchCsrfCookie } from '@/features/auth/api'

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
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h1 class="text-center text-xl font-semibold text-gray-900">Reset your password</h1>

    <p class="text-sm text-gray-600">
      Enter your email address and we'll send you a link to reset your password.
    </p>

    <div v-if="successMessage" class="rounded-md bg-green-50 p-3 text-sm text-green-700">
      {{ successMessage }}
    </div>

    <div>
      <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
      <input
        id="email"
        v-model="email"
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

    <button
      type="submit"
      :disabled="submitting"
      class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
      {{ submitting ? 'Sending...' : 'Send reset link' }}
    </button>

    <p class="text-center text-sm text-gray-600">
      <router-link :to="{ name: 'login' }" class="text-primary-600 hover:text-primary-500">
        Back to sign in
      </router-link>
    </p>
  </form>
</template>
