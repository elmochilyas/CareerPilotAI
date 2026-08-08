<script setup lang="ts">
import { useRoute } from 'vue-router'
import { LayoutDashboard, User, FileText, Briefcase, LogOut } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'

defineOptions({ name: 'AppSidebar' })

defineProps<{
  collapsed?: boolean
}>()

const route = useRoute()
const auth = useAuthStore()

const navItems = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/profile', label: 'Profile', icon: User },
  { to: '/profile/cv', label: 'CV', icon: FileText },
  { to: '/opportunities', label: 'Opportunities', icon: Briefcase },
]

function isActive(href: string): boolean {
  if (href === '/') return route.path === '/'
  return route.path.startsWith(href)
}

function getInitial(name: string): string {
  return name.charAt(0).toUpperCase()
}

async function handleLogout(): Promise<void> {
  await auth.logout()
}
</script>

<template>
  <aside
    class="flex h-full flex-col border-r bg-[var(--surface-primary)]"
    :aria-label="collapsed ? 'Navigation (collapsed)' : 'Main navigation'"
  >
    <div class="flex items-center gap-2 px-4 pt-5 pb-4">
      <span
        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-600)] text-xs font-bold text-[var(--text-on-primary)]"
      >
        CP
      </span>
      <Transition name="fade">
        <span v-if="!collapsed" class="text-lg font-bold tracking-tight text-[var(--text-primary)]">
          CareerPilot
        </span>
      </Transition>
    </div>

    <nav class="flex-1 space-y-1 px-3 pt-2">
      <router-link
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="group flex items-center rounded-lg px-3 py-2.5 text-sm font-medium no-underline transition-colors"
        :class="[
          isActive(item.to)
            ? 'bg-[var(--color-primary-50)] text-[var(--color-primary-700)]'
            : 'text-[var(--text-secondary)] hover:bg-[var(--surface-secondary)] hover:text-[var(--text-primary)]',
          collapsed ? 'justify-center' : 'gap-3',
        ]"
        :title="collapsed ? item.label : undefined"
      >
        <component
          :is="item.icon"
          :size="20"
          :stroke-width="isActive(item.to) ? 2.5 : 2"
          class="shrink-0"
        />
        <Transition name="fade">
          <span v-if="!collapsed">{{ item.label }}</span>
        </Transition>
      </router-link>
    </nav>

    <div class="border-t px-3 py-4">
      <div
        class="flex items-center gap-3 px-3 pb-3"
        :class="collapsed ? 'justify-center px-0' : ''"
      >
        <span
          class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary-100)] text-sm font-semibold text-[var(--color-primary-700)]"
        >
          {{ auth.user?.full_name ? getInitial(auth.user.full_name) : '?' }}
        </span>
        <Transition name="fade">
          <div v-if="!collapsed" class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-[var(--text-primary)]">
              {{ auth.user?.full_name }}
            </p>
            <p class="truncate text-xs text-[var(--text-muted)]">
              {{ auth.user?.email }}
            </p>
          </div>
        </Transition>
      </div>
      <button
        type="button"
        class="flex w-full items-center rounded-lg px-3 py-2.5 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--surface-secondary)] hover:text-[var(--text-primary)]"
        :class="collapsed ? 'justify-center' : 'gap-3'"
        :title="collapsed ? 'Logout' : undefined"
        @click="handleLogout"
      >
        <LogOut :size="20" class="shrink-0" />
        <Transition name="fade">
          <span v-if="!collapsed">Logout</span>
        </Transition>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity var(--duration-fast) var(--ease-default);
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
