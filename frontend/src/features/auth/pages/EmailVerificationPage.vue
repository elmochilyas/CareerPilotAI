<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { resendVerificationEmail, fetchCsrfCookie } from '@/features/auth/api'
import Button from '@/components/ui/Button.vue'

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
    <h1 class="text-lg font-bold tracking-tight text-slate-900">Email Verification</h1>

    <div v-if="status === 'verifying'" class="space-y-4">
      <p class="text-sm text-slate-500">Please check your email for a verification link.</p>
      <div
        v-if="resentMessage"
        class="flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
      >
        <CheckCircle class="size-4 shrink-0 text-emerald-500" aria-hidden="true" />
        {{ resentMessage }}
      </div>
      <button
        type="button"
        :disabled="resending"
        class="text-sm font-medium text-primary-600 hover:text-primary-500 disabled:opacity-50"
        @click="resend"
      >
        {{ resending ? 'Sending...' : 'Resend verification email' }}
      </button>
    </div>

    <div
      v-if="status === 'verified'"
      class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-5"
    >
      <p class="text-sm text-emerald-700">{{ message }}</p>
      <div class="mt-4">
        <Button @click="goHome">Go to home</Button>
      </div>
    </div>

    <div v-if="status === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-5">
      <p class="text-sm text-red-700">{{ message }}</p>
      <button
        type="button"
        :disabled="resending"
        class="mt-4 text-sm font-medium text-primary-600 hover:text-primary-500 disabled:opacity-50"
        @click="resend"
      >
        {{ resending ? 'Sending...' : 'Resend verification email' }}
      </button>
    </div>
  </div>
</template>
