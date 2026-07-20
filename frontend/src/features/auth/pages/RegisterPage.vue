<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { RegisterData } from '@/features/auth/types'

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
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h1 class="text-center text-xl font-semibold text-gray-900">Create your account</h1>

    <div>
      <label for="full_name" class="block text-sm font-medium text-gray-700">Full name</label>
      <input
        id="full_name"
        v-model="form.full_name"
        type="text"
        autocomplete="name"
        required
        :aria-describedby="errors.full_name ? 'full_name-error' : undefined"
        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        :class="{ 'border-red-500': errors.full_name }"
      />
      <p v-if="errors.full_name" id="full_name-error" class="mt-1 text-xs text-red-600">
        {{ errors.full_name[0] }}
      </p>
    </div>

    <div>
      <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
      <input
        id="email"
        v-model="form.email"
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

    <div>
      <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
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
        >Confirm password</label
      >
      <input
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        autocomplete="new-password"
        required
        :aria-describedby="errors.password ? 'password-error' : undefined"
        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        :class="{ 'border-red-500': errors.password }"
      />
    </div>

    <button
      type="submit"
      :disabled="submitting"
      class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
      {{ submitting ? 'Creating account...' : 'Create account' }}
    </button>

    <p class="text-center text-sm text-gray-600">
      Already have an account?
      <router-link :to="{ name: 'login' }" class="text-primary-600 hover:text-primary-500">
        Sign in
      </router-link>
    </p>
  </form>
</template>
