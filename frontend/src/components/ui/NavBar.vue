<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'
import { Menu, X, LogOut } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import Button from './Button.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const mobileMenuOpen = ref(false)

async function handleLogout(): Promise<void> {
  try {
    await auth.logout()
  } catch {
    // defensive
  }
  mobileMenuOpen.value = false
  await router.push({ name: 'login' })
}

const navLinks = [
  { name: 'home', label: 'Home' },
  { name: 'profile', label: 'Profile' },
]
</script>

<template>
  <nav class="border-b border-slate-200 bg-white" aria-label="Main navigation">
    <div
      class="mx-auto flex min-h-14 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8"
    >
      <RouterLink
        to="/"
        class="flex items-center text-base font-semibold text-slate-900 no-underline hover:text-primary-600"
      >
        CareerPilot
      </RouterLink>

      <template v-if="auth.isAuthenticated">
        <div class="hidden items-center gap-1 sm:flex">
          <RouterLink
            v-for="link in navLinks"
            :key="link.name"
            :to="{ name: link.name }"
            class="rounded-lg px-3 py-2 text-sm font-medium no-underline transition-colors"
            :class="
              route.name === link.name
                ? 'bg-primary-50 text-primary-700'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
            "
          >
            {{ link.label }}
          </RouterLink>
        </div>

        <div class="hidden items-center gap-3 sm:flex">
          <span class="max-w-40 truncate text-sm text-slate-500">
            {{ auth.user?.email }}
          </span>
          <Button variant="outline" size="sm" :disabled="auth.loading" @click="handleLogout">
            {{ auth.loading ? 'Logging out...' : 'Logout' }}
          </Button>
        </div>

        <button
          type="button"
          class="flex size-9 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 sm:hidden"
          aria-label="Toggle menu"
          @click="mobileMenuOpen = !mobileMenuOpen"
        >
          <Menu v-if="!mobileMenuOpen" :size="20" />
          <X v-else :size="20" />
        </button>
      </template>

      <template v-else>
        <div class="flex items-center gap-2">
          <RouterLink
            :to="{ name: 'login' }"
            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 no-underline hover:text-primary-600"
          >
            Login
          </RouterLink>
          <RouterLink
            :to="{ name: 'register' }"
            class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white no-underline shadow-sm hover:bg-primary-700"
          >
            Register
          </RouterLink>
        </div>
      </template>
    </div>

    <Transition name="mobile-menu">
      <div
        v-if="mobileMenuOpen && auth.isAuthenticated"
        class="border-t border-slate-200 bg-white sm:hidden"
      >
        <div class="space-y-1 px-4 py-3">
          <RouterLink
            v-for="link in navLinks"
            :key="link.name"
            :to="{ name: link.name }"
            class="flex rounded-lg px-3 py-2.5 text-sm font-medium no-underline transition-colors"
            :class="
              route.name === link.name
                ? 'bg-primary-50 text-primary-700'
                : 'text-slate-600 hover:bg-slate-100'
            "
            @click="mobileMenuOpen = false"
          >
            {{ link.label }}
          </RouterLink>
        </div>
        <div class="border-t border-slate-100 px-4 py-3">
          <div class="mb-2 text-sm text-slate-500">
            {{ auth.user?.email }}
          </div>
          <button
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
            @click="handleLogout"
          >
            <LogOut :size="16" />
            Logout
          </button>
        </div>
      </div>
    </Transition>
  </nav>
</template>

<style scoped>
.mobile-menu-enter-active,
.mobile-menu-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.mobile-menu-enter-from,
.mobile-menu-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
