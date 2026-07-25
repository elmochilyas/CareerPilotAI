<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { sendForgotPasswordLink, fetchCsrfCookie } from '@/features/auth/api'
import Button from '@/components/ui/Button.vue'
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
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h1 class="text-center text-lg font-semibold text-slate-900">Reset your password</h1>

    <p class="text-sm text-slate-600">
      Enter your email address and we'll send you a link to reset your password.
    </p>

    <div
      v-if="successMessage"
      class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"
    >
      {{ successMessage }}
    </div>

    <Input
      v-model="email"
      name="email"
      label="Email"
      type="email"
      autocomplete="email"
      required
      :error="errors.email?.[0]"
    />

    <Button type="submit" :disabled="submitting" class="w-full">
      {{ submitting ? 'Sending...' : 'Send reset link' }}
    </Button>

    <p class="text-center text-sm text-slate-600">
      <RouterLink :to="{ name: 'login' }" class="text-primary-600 hover:text-primary-500">
        Back to sign in
      </RouterLink>
    </p>
  </form>
</template>
