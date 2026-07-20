<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { resendVerificationEmail } from '@/features/auth/api'
import { fetchCsrfCookie } from '@/features/auth/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const status = ref<'verifying' | 'verified' | 'error'>('verifying')
const message = ref('')
const resending = ref(false)
const resentMessage = ref('')

onMounted(() => {
  if (route.query.verified === '1') {
    status.value = 'verified'
    message.value = 'Your email has been verified successfully.'
    if (auth.user) {
      auth.fetchUser()
    }
  } else if (route.query.verified === '0') {
    status.value = 'error'
    message.value = (route.query.message as string) || 'Invalid verification link.'
  } else {
    status.value = 'verifying'
  }
})

async function resend(): Promise<void> {
  resending.value = true
  resentMessage.value = ''

  try {
    await fetchCsrfCookie()
    await resendVerificationEmail()
    resentMessage.value = 'A new verification email has been sent.'
  } catch {
    resentMessage.value = 'Failed to resend verification email. Please try again.'
  } finally {
    resending.value = false
  }
}

async function goHome(): Promise<void> {
  await router.push({ name: 'home' })
}
</script>

<template>
  <div class="space-y-6 text-center">
    <h1 class="text-xl font-semibold text-gray-900">Email Verification</h1>

    <div v-if="status === 'verifying'" class="space-y-4">
      <p class="text-sm text-gray-600">Please check your email for a verification link.</p>
      <div v-if="resentMessage" class="rounded-md bg-green-50 p-3 text-sm text-green-700">
        {{ resentMessage }}
      </div>
      <button
        type="button"
        :disabled="resending"
        @click="resend"
        class="text-sm text-primary-600 hover:text-primary-500 disabled:opacity-50"
      >
        {{ resending ? 'Sending...' : 'Resend verification email' }}
      </button>
    </div>

    <div v-if="status === 'verified'" class="rounded-md bg-green-50 p-4">
      <p class="text-sm text-green-700">{{ message }}</p>
      <button
        type="button"
        @click="goHome"
        class="mt-4 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
      >
        Go to home
      </button>
    </div>

    <div v-if="status === 'error'" class="rounded-md bg-red-50 p-4">
      <p class="text-sm text-red-700">{{ message }}</p>
      <button
        type="button"
        @click="resend"
        class="mt-4 text-sm text-primary-600 hover:text-primary-500"
      >
        Resend verification email
      </button>
    </div>
  </div>
</template>
