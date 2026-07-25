<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { RegisterData } from '@/features/auth/types'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const router = useRouter()
const auth = useAuthStore()

const form = ref<RegisterData>({
  full_name: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const errors = ref<Record<string, string[]>>({})
const submitting = ref(false)

async function handleSubmit(): Promise<void> {
  errors.value = {}
  submitting.value = true
  try {
    await auth.register(form.value)
    await router.push({ name: 'login' })
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

const fieldError = (field: string) => errors.value[field]?.[0]
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h1 class="text-center text-lg font-semibold text-slate-900">Create your account</h1>

    <Input
      v-model="form.full_name"
      name="full_name"
      label="Full name"
      autocomplete="name"
      required
      :error="fieldError('full_name')"
    />

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
      autocomplete="new-password"
      required
      :error="fieldError('password')"
    />

    <Input
      v-model="form.password_confirmation"
      name="password_confirmation"
      label="Confirm password"
      type="password"
      autocomplete="new-password"
      required
      :error="fieldError('password')"
    />

    <Button type="submit" :disabled="submitting" class="w-full">
      {{ submitting ? 'Creating account...' : 'Create account' }}
    </Button>

    <p class="text-center text-sm text-slate-600">
      Already have an account?
      <RouterLink :to="{ name: 'login' }" class="text-primary-600 hover:text-primary-500">
        Sign in
      </RouterLink>
    </p>
  </form>
</template>
