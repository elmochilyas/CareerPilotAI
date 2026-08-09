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
    <h1 class="text-[var(--text-xl)] font-semibold tracking-tight text-[var(--text-primary)]">
      Email Verification
    </h1>

    <div v-if="status === 'verifying'" class="space-y-4">
      <p class="text-[var(--text-base)] text-[var(--text-secondary)]">
        Please check your email for a verification link.
      </p>
      <div
        v-if="resentMessage"
        class="flex items-center justify-center gap-2 rounded-[var(--radius-xl)] bg-[var(--color-success-50)] px-4 py-3 text-[var(--text-base)] text-[var(--color-success-700)]"
        style="box-shadow: var(--shadow-neo-inset)"
      >
        <CheckCircle class="size-4 shrink-0 text-[var(--color-success-500)]" aria-hidden="true" />
        {{ resentMessage }}
      </div>
      <button
        type="button"
        :disabled="resending"
        class="text-[var(--text-sm)] font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)] disabled:opacity-50"
        @click="resend"
      >
        {{ resending ? 'Sending...' : 'Resend verification email' }}
      </button>
    </div>

    <div
      v-if="status === 'verified'"
      class="rounded-[var(--radius-xl)] bg-[var(--color-success-50)] px-4 py-5"
      style="box-shadow: var(--shadow-neo-raised)"
    >
      <p class="text-[var(--text-base)] text-[var(--color-success-700)]">{{ message }}</p>
      <div class="mt-4">
        <Button @click="goHome">Go to home</Button>
      </div>
    </div>

    <div
      v-if="status === 'error'"
      class="rounded-[var(--radius-xl)] bg-[var(--color-error-50)] px-4 py-5"
      style="box-shadow: var(--shadow-neo-raised)"
    >
      <p class="text-[var(--text-base)] text-[var(--color-error-700)]">{{ message }}</p>
      <button
        type="button"
        :disabled="resending"
        class="mt-4 text-[var(--text-sm)] font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)] disabled:opacity-50"
        @click="resend"
      >
        {{ resending ? 'Sending...' : 'Resend verification email' }}
      </button>
    </div>
  </div>
</template>
