<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { LayoutDashboard, User, FileText, Briefcase, LogOut } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import logo from '@/assets/images/logo.png'

defineOptions({ name: 'AppSidebar' })

defineProps<{
  collapsed?: boolean
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const navItems = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/profile', label: 'Profile', icon: User },
  { to: '/cv', label: 'CV', icon: FileText },
  { to: '/opportunities', label: 'Opportunities', icon: Briefcase },
]

function isActive(href: string): boolean {
  if (href === '/') return route.path === '/'
  const path = route.path
  return path === href || path.startsWith(href + '/')
}

function getInitial(name: string): string {
  return name.charAt(0).toUpperCase()
}

async function handleLogout(): Promise<void> {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <aside
    class="sidebar flex h-full flex-col"
    :aria-label="collapsed ? 'Navigation (collapsed)' : 'Main navigation'"
  >
    <div class="flex items-center gap-3 px-4 pt-6 pb-5">
      <img :src="logo" alt="CareerPilot" class="sidebar-logo" />
    </div>

    <nav class="flex-1 space-y-1.5 px-3 pt-2">
      <router-link
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="sidebar-nav-item group flex items-center rounded-xl text-sm font-medium no-underline transition-all duration-200"
        :class="[
          isActive(item.to)
            ? 'sidebar-nav-item--active text-white'
            : 'text-white/70 hover:text-white',
          collapsed ? 'justify-center px-2 py-3' : 'gap-3 px-3 py-3',
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

    <div class="mt-auto px-3 py-4">
      <div
        class="sidebar-user-card flex items-center gap-3 px-3 py-3"
        :class="collapsed ? 'justify-center px-2' : ''"
      >
        <span
          class="sidebar-avatar flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
        >
          {{ auth.user?.full_name ? getInitial(auth.user.full_name) : '?' }}
        </span>
        <Transition name="fade">
          <div v-if="!collapsed" class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-white">
              {{ auth.user?.full_name }}
            </p>
            <p class="truncate text-xs text-white/60">
              {{ auth.user?.email }}
            </p>
          </div>
        </Transition>
      </div>
      <button
        type="button"
        class="sidebar-logout-btn flex w-full items-center rounded-xl text-sm font-medium text-white/70 transition-all duration-200 hover:text-white"
        :class="collapsed ? 'justify-center px-2 py-3' : 'gap-3 px-3 py-3'"
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
.sidebar {
  background: linear-gradient(180deg, #4a3ab5 0%, #2a1f6e 100%);
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-neo-sidebar);
}

.sidebar::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    rgba(255, 255, 255, 0.06) 0%,
    rgba(255, 255, 255, 0.02) 50%,
    rgba(255, 255, 255, 0) 100%
  );
  pointer-events: none;
}

.sidebar-logo {
  height: 2.5rem;
  width: auto;
}

.sidebar-nav-item {
  background: rgba(255, 255, 255, 0.06);
  box-shadow: var(--shadow-neo-sidebar);
  border: none;
}

.sidebar-nav-item:hover:not(.sidebar-nav-item--active) {
  background: rgba(255, 255, 255, 0.1);
  box-shadow: var(--shadow-neo-sidebar);
}

.sidebar-nav-item--active {
  background: rgba(255, 255, 255, 0.15);
  box-shadow: var(--shadow-neo-sidebar-inset);
  color: white;
}

.sidebar-user-card {
  background: rgba(255, 255, 255, 0.08);
  box-shadow: var(--shadow-neo-sidebar);
  border-radius: var(--radius-xl);
  border: none;
}

.sidebar-avatar {
  background: linear-gradient(135deg, var(--color-primary-400) 0%, var(--color-primary-600) 100%);
  box-shadow:
    0 2px 8px rgba(0, 0, 0, 0.2),
    0 0 0 2px rgba(255, 255, 255, 0.15);
}

.sidebar-logout-btn {
  background: transparent;
  box-shadow: none;
}

.sidebar-logout-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  box-shadow: none;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity var(--duration-fast) var(--ease-default);
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
