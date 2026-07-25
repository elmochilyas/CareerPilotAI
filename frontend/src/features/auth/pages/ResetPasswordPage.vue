<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { resetPassword, fetchCsrfCookie } from '@/features/auth/api'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

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
    <h1 class="text-center text-lg font-semibold text-slate-900">Set new password</h1>

    <input type="hidden" name="email" :value="form.email" />
    <input type="hidden" name="token" :value="form.token" />

    <Input
      v-model="form.password"
      name="password"
      label="New password"
      type="password"
      autocomplete="new-password"
      required
      :error="errors.password?.[0]"
    />

    <Input
      v-model="form.password_confirmation"
      name="password_confirmation"
      label="Confirm new password"
      type="password"
      autocomplete="new-password"
      required
      :error="errors.password?.[0]"
    />

    <Button type="submit" :disabled="submitting" class="w-full">
      {{ submitting ? 'Resetting...' : 'Reset password' }}
    </Button>

    <p class="text-center text-sm text-slate-600">
      <RouterLink :to="{ name: 'login' }" class="text-primary-600 hover:text-primary-500">
        Back to sign in
      </RouterLink>
    </p>
  </form>
</template>
