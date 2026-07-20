<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

async function handleLogout(): Promise<void> {
  try {
    await auth.logout()
  } catch {
    // Store handles errors internally; this is defensive
  }
  await router.push({ name: 'login' })
}
</script>

<template>
  <nav class="border-b border-gray-200 bg-white" aria-label="Main navigation">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
      <RouterLink
        to="/"
        class="text-lg font-semibold text-gray-900 no-underline hover:text-primary-600"
      >
        CareerPilot
      </RouterLink>

      <div class="flex items-center gap-4">
        <template v-if="auth.isAuthenticated">
          <RouterLink
            to="/"
            class="text-sm font-medium text-gray-600 no-underline hover:text-primary-600"
          >
            Home
          </RouterLink>

          <span class="text-sm text-gray-500">{{ auth.user?.email }}</span>

          <button
            type="button"
            :disabled="auth.loading"
            class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
            @click="handleLogout"
          >
            {{ auth.loading ? 'Logging out...' : 'Logout' }}
          </button>
        </template>

        <template v-else>
          <RouterLink
            :to="{ name: 'login' }"
            class="text-sm font-medium text-gray-600 no-underline hover:text-primary-600"
          >
            Login
          </RouterLink>

          <RouterLink
            :to="{ name: 'register' }"
            class="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white no-underline shadow-sm hover:bg-primary-500"
          >
            Register
          </RouterLink>
        </template>
      </div>
    </div>
  </nav>
</template>
