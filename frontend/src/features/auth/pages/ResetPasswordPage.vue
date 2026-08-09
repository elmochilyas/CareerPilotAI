<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { resetPassword, fetchCsrfCookie } from '@/features/auth/api'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
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
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <div class="text-center">
      <h1 class="text-[var(--text-xl)] font-semibold tracking-tight text-[var(--text-primary)]">
        Set new password
      </h1>
      <p class="mt-1 text-[var(--text-base)] text-[var(--text-secondary)]">
        Enter your new password below.
      </p>
    </div>

    <input type="hidden" name="email" :value="form.email" />
    <input type="hidden" name="token" :value="form.token" />

    <FormField label="New password" :error="errors.password?.[0]" required>
      <Input
        v-model="form.password"
        name="password"
        type="password"
        autocomplete="new-password"
        required
      />
    </FormField>

    <FormField label="Confirm new password" :error="errors.password_confirmation?.[0]" required>
      <Input
        v-model="form.password_confirmation"
        name="password_confirmation"
        type="password"
        autocomplete="new-password"
        required
      />
    </FormField>

    <Button type="submit" :loading="submitting" :disabled="submitting" class="w-full">
      Reset password
    </Button>

    <p class="text-center text-[var(--text-base)] text-[var(--text-secondary)]">
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
      >
        Back to sign in
      </RouterLink>
    </p>
  </form>
</template>
