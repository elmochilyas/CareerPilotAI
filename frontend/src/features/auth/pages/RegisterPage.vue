<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { RegisterData } from '@/features/auth/types'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
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

const passwordStrength = computed(() => {
  const pw = form.value.password
  if (!pw) return { score: 0, label: '', color: '' }

  let score = 0
  if (pw.length >= 8) score++
  if (pw.length >= 12) score++
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++
  if (/\d/.test(pw)) score++
  if (/[^a-zA-Z0-9]/.test(pw)) score++

  if (score <= 1) return { score, label: 'Weak', color: 'bg-red-500' }
  if (score <= 2) return { score, label: 'Fair', color: 'bg-amber-500' }
  if (score <= 3) return { score, label: 'Good', color: 'bg-yellow-500' }
  if (score <= 4) return { score, label: 'Strong', color: 'bg-primary-500' }
  return { score, label: 'Very strong', color: 'bg-emerald-500' }
})

const passwordStrengthWidth = computed(() => `${(passwordStrength.value.score / 5) * 100}%`)

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
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <div class="text-center">
      <h1 class="text-[var(--text-xl)] font-semibold tracking-tight text-[var(--text-primary)]">
        Create your account
      </h1>
      <p class="mt-1 text-[var(--text-sm)] text-[var(--text-secondary)]">
        Get started with CareerPilot
      </p>
    </div>

    <FormField label="Full name" :error="fieldError('full_name')" required>
      <Input
        v-model="form.full_name"
        name="full_name"
        autocomplete="name"
        placeholder="Jane Doe"
        required
      />
    </FormField>

    <FormField label="Email" :error="fieldError('email')" required>
      <Input
        v-model="form.email"
        name="email"
        type="email"
        autocomplete="email"
        placeholder="you@example.com"
        required
      />
    </FormField>

    <FormField label="Password" :error="fieldError('password')" required>
      <Input
        v-model="form.password"
        name="password"
        type="password"
        autocomplete="new-password"
        required
      />
      <div v-if="form.password" class="mt-2 space-y-1.5">
        <div class="flex gap-1">
          <div
            class="h-1.5 flex-1 overflow-hidden rounded-full bg-[var(--surface-page)]"
            style="box-shadow: var(--shadow-neo-inset)"
          >
            <div
              class="h-full rounded-full transition-all duration-300 ease-out"
              :class="passwordStrength.color"
              :style="{ width: passwordStrengthWidth }"
            />
          </div>
        </div>
        <p class="text-[var(--text-sm)] text-[var(--text-secondary)]">
          Password strength:
          <span class="font-medium text-[var(--text-primary)]">{{ passwordStrength.label }}</span>
        </p>
      </div>
    </FormField>

    <FormField label="Confirm password" :error="fieldError('password_confirmation')" required>
      <Input
        v-model="form.password_confirmation"
        name="password_confirmation"
        type="password"
        autocomplete="new-password"
        required
      />
    </FormField>

    <Button type="submit" :loading="submitting" :disabled="submitting" class="w-full">
      Create account
    </Button>

    <p class="text-center text-[var(--text-base)] text-[var(--text-secondary)]">
      Already have an account?
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
      >
        Sign in
      </RouterLink>
    </p>
  </form>
</template>
