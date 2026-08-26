<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard,
  User,
  FileText,
  Briefcase,
  LogOut,
  PanelLeftClose,
  PanelLeftOpen,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import logo from '@/assets/images/logo.png'

defineOptions({ name: 'AppSidebar' })

const props = withDefaults(
  defineProps<{
    collapsed?: boolean
    cvBadge?: number | string | null
    opportunitiesBadge?: number | string | null
    completion?: number | null
  }>(),
  {
    collapsed: false,
    cvBadge: null,
    opportunitiesBadge: null,
    completion: null,
  },
)

const emit = defineEmits<{
  'update:collapsed': [value: boolean]
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const navItems = computed(() => [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, badge: null as string | number | null },
  { to: '/profile', label: 'Profile', icon: User, badge: null as string | number | null },
  {
    to: '/cv',
    label: 'CV',
    icon: FileText,
    badge: props.cvBadge != null && props.cvBadge !== '' ? String(props.cvBadge) : null,
  },
  {
    to: '/opportunities',
    label: 'Opportunities',
    icon: Briefcase,
    badge:
      props.opportunitiesBadge != null && props.opportunitiesBadge !== ''
        ? String(props.opportunitiesBadge)
        : null,
  },
])

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

function toggleCollapsed(): void {
  emit('update:collapsed', !props.collapsed)
}

const completionClamped = computed(() => {
  if (props.completion == null || Number.isNaN(props.completion)) return null
  return Math.max(0, Math.min(100, Math.round(props.completion)))
})

const avatarRingStyle = computed(() => {
  if (completionClamped.value == null) return {}
  return {
    background: `conic-gradient(from 0deg, #d8f255 ${completionClamped.value}%, rgba(255,255,255,0.18) ${completionClamped.value}% 100%)`,
  } as Record<string, string>
})
</script>

<template>
  <aside
    class="sidebar flex flex-col"
    :aria-label="collapsed ? 'Navigation (collapsed)' : 'Main navigation'"
  >
    <!-- Collapse toggle — top, centered when collapsed -->
    <button
      type="button"
      class="sidebar-collapse-toggle absolute z-20 hidden size-7 items-center justify-center rounded-lg border border-white/10 bg-white/[0.08] text-white/70 backdrop-blur-[var(--sidebar-glass-blur)] transition-[top,background-color,border-color,color] duration-300 ease-out-expo hover:bg-white/[0.14] hover:text-white lg:flex"
      :class="collapsed ? 'top-2 left-1/2 -translate-x-1/2' : 'top-5 right-2'"
      :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      :title="collapsed ? 'Expand' : 'Collapse'"
      @click="toggleCollapsed"
    >
      <PanelLeftClose
        :size="14"
        class="absolute shrink-0 transition-opacity duration-150 ease-out-expo"
        :class="collapsed ? 'opacity-0' : 'opacity-100'"
        aria-hidden="true"
      />
      <PanelLeftOpen
        :size="14"
        class="absolute shrink-0 transition-opacity duration-150 ease-out-expo"
        :class="collapsed ? 'opacity-100' : 'opacity-0'"
        aria-hidden="true"
      />
    </button>

    <!-- Runway spine — hidden when collapsed (use dots on nav pills instead) -->
    <div
      class="runway-spine transition-opacity duration-150 ease-out-expo"
      :class="collapsed ? 'opacity-0' : 'opacity-100'"
      aria-hidden="true"
    >
      <div class="runway-spine__track" />
      <div
        v-for="item in navItems"
        :key="'spine-' + item.to"
        class="runway-spine__dot"
        :class="{ 'runway-spine__dot--active': isActive(item.to) }"
      />
    </div>

    <!-- Logo -->
    <div
      class="sidebar-brand relative z-10 flex h-[10.75rem] shrink-0 items-center justify-center pt-12 pb-6 transition-[margin,padding] duration-300 ease-out-expo"
      :class="collapsed ? '-mt-3 px-3' : 'px-4'"
    >
      <img
        :src="logo"
        :alt="collapsed ? '' : 'CareerPilot'"
        class="sidebar-logo transition-[opacity,transform] duration-200 ease-out-expo"
        :class="collapsed ? 'scale-95 opacity-0' : 'scale-100 opacity-100'"
        :aria-hidden="collapsed"
        width="148"
        height="99"
      />
      <div
        class="sidebar-monogram absolute flex size-8 shrink-0 items-center justify-center rounded-lg bg-white text-sm font-bold tracking-tight text-[var(--color-primary-700)] shadow-sm transition-[opacity,transform] duration-200 ease-out-expo"
        :class="collapsed ? 'scale-100 opacity-100' : 'scale-90 opacity-0'"
        aria-hidden="true"
      >
        CP
      </div>
    </div>

    <!-- Eyebrow + Nav -->
    <div class="relative z-10 flex-1 px-3">
      <p
        class="sidebar-eyebrow mb-2 h-[15px] px-3 text-[10px] font-semibold leading-[15px] uppercase tracking-[0.12em] text-white/45 transition-opacity duration-150 ease-out-expo"
        :class="collapsed ? 'opacity-0' : 'opacity-100'"
        :aria-hidden="collapsed"
      >
        Navigate
      </p>
      <nav class="space-y-1.5" :aria-label="collapsed ? 'Collapsed navigation' : undefined">
        <router-link
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="sidebar-nav-item group relative flex items-center py-3 rounded-xl text-sm font-medium no-underline transition-[gap,padding,background-color,border-color,box-shadow,transform] duration-300 ease-out-expo"
          :class="[
            isActive(item.to)
              ? 'sidebar-nav-item--active text-white'
              : 'text-white/65 hover:text-white',
            collapsed ? 'gap-0 px-2' : 'gap-3 px-3',
          ]"
          :title="collapsed ? item.label : undefined"
        >
          <span
            v-if="isActive(item.to)"
            class="active-bar transition-opacity duration-150 ease-out-expo"
            :class="collapsed ? 'opacity-0' : 'opacity-100'"
            aria-hidden="true"
          />
          <component
            :is="item.icon"
            :size="20"
            :stroke-width="isActive(item.to) ? 2.5 : 2"
            class="shrink-0 transition-transform duration-200 group-hover:scale-[1.03]"
          />
          <span
            class="sidebar-nav-label flex min-w-0 flex-1 items-center gap-2 overflow-hidden whitespace-nowrap transition-[max-width,opacity,transform] duration-200 ease-out-expo"
            :class="
              collapsed
                ? 'max-w-0 -translate-x-1 opacity-0'
                : 'max-w-[11rem] translate-x-0 opacity-100'
            "
            :aria-hidden="collapsed"
          >
            <span class="truncate">{{ item.label }}</span>
            <span
              v-if="item.badge"
              class="ml-auto inline-flex min-w-[1.25rem] justify-center rounded-full bg-white px-1.5 py-0.5 text-[11px] font-bold leading-none text-[var(--color-primary-700)] shadow-sm"
            >
              {{ item.badge }}
            </span>
          </span>
          <!-- Collapsed badge dot -->
          <span
            v-if="item.badge"
            class="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-white text-[10px] font-bold leading-none text-[var(--color-primary-700)] shadow-sm ring-1 ring-black/5 transition-[opacity,transform] duration-150 ease-out-expo"
            :class="collapsed ? 'scale-100 opacity-100' : 'scale-75 opacity-0'"
          >
            {{ item.badge }}
          </span>
          <!-- Collapsed active lime dot below icon -->
          <span
            class="collapsed-dot"
            :class="{
              'collapsed-dot--visible': collapsed,
              'collapsed-dot--active': isActive(item.to),
            }"
            aria-hidden="true"
          />
        </router-link>
      </nav>
    </div>

    <!-- Footer -->
    <div
      class="sidebar-footer relative z-10 mt-auto space-y-3 px-3 py-4 transition-transform duration-300 ease-out-expo"
      :class="{ 'translate-y-3': collapsed }"
    >
      <!-- User card -->
      <div
        class="sidebar-user-card flex items-center py-3 transition-[gap,padding] duration-300 ease-out-expo"
        :class="collapsed ? 'gap-0 px-0' : 'gap-3 px-3'"
      >
        <span class="sidebar-avatar-wrap relative flex size-9 shrink-0 items-center justify-center">
          <span
            v-if="completionClamped != null"
            class="absolute inset-0 rounded-full"
            :style="avatarRingStyle"
            aria-hidden="true"
          />
          <span
            class="sidebar-avatar relative flex size-9 items-center justify-center rounded-full text-sm font-semibold text-white"
          >
            {{ auth.user?.full_name ? getInitial(auth.user.full_name) : '?' }}
          </span>
        </span>
        <div
          class="sidebar-user-details min-w-0 flex-1 overflow-hidden whitespace-nowrap transition-[max-width,opacity,transform] duration-200 ease-out-expo"
          :class="
            collapsed
              ? 'max-w-0 -translate-x-1 opacity-0'
              : 'max-w-[10rem] translate-x-0 opacity-100'
          "
          :aria-hidden="collapsed"
        >
          <p class="truncate text-sm font-medium text-white">
            {{ auth.user?.full_name }}
          </p>
          <p class="truncate text-xs text-white/60">
            {{ auth.user?.email }}
          </p>
          <p
            v-if="completionClamped != null"
            class="mt-0.5 font-mono text-[10px] uppercase tracking-[0.08em] text-white/50"
          >
            {{ completionClamped }}% complete
          </p>
        </div>
      </div>

      <button
        type="button"
        class="sidebar-logout-btn flex w-full items-center py-3 rounded-xl text-sm font-medium text-white/60 transition-[gap,padding,background-color,border-color,color] duration-300 ease-out-expo hover:bg-white/[0.08] hover:text-white"
        :class="collapsed ? 'gap-0 px-2' : 'gap-3 px-3'"
        :title="collapsed ? 'Logout' : undefined"
        @click="handleLogout"
      >
        <LogOut :size="18" class="shrink-0" />
        <span
          class="sidebar-logout-label overflow-hidden whitespace-nowrap transition-[max-width,opacity,transform] duration-200 ease-out-expo"
          :class="
            collapsed ? 'max-w-0 -translate-x-1 opacity-0' : 'max-w-24 translate-x-0 opacity-100'
          "
          :aria-hidden="collapsed"
        >
          Logout
        </span>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  background: linear-gradient(
    180deg,
    var(--sidebar-gradient-start) 0%,
    var(--sidebar-gradient-end) 100%
  );
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

/* Runway spine — vertical line + traveling lime dots */
.runway-spine {
  position: absolute;
  left: 18px;
  top: 92px;
  bottom: 168px;
  width: 1px;
  z-index: 0;
  pointer-events: none;
}

.sidebar:has(.sidebar-nav-item) .runway-spine {
  display: block;
}

.runway-spine__track {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
  border-radius: 9999px;
}

.runway-spine__dot {
  position: absolute;
  left: 50%;
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.22);
  transform: translateX(-50%);
  transition:
    background var(--duration-normal) var(--ease-default),
    box-shadow var(--duration-normal) var(--ease-default),
    transform var(--duration-normal) var(--ease-spring);
}

/* distribute 4 dots evenly along track */
.runway-spine__dot:nth-child(2) {
  top: 8%;
}
.runway-spine__dot:nth-child(3) {
  top: 34%;
}
.runway-spine__dot:nth-child(4) {
  top: 60%;
}
.runway-spine__dot:nth-child(5) {
  top: 86%;
}

.runway-spine__dot--active {
  background: #d8f255;
  box-shadow:
    0 0 0 4px rgba(216, 242, 85, 0.18),
    0 0 12px rgba(216, 242, 85, 0.55);
  transform: translateX(-50%) scale(1.25);
}

.sidebar-logo {
  width: 78%;
  max-width: 148px;
  height: auto;
  filter: drop-shadow(0 1px 6px rgba(0, 0, 0, 0.18));
}

.sidebar-nav-item {
  position: relative;
  background: rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(var(--sidebar-glass-blur));
  -webkit-backdrop-filter: blur(var(--sidebar-glass-blur));
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: var(--shadow-neo-sidebar);
}

.sidebar-nav-item:hover:not(.sidebar-nav-item--active) {
  background: var(--sidebar-glass-hover);
  border-color: rgba(255, 255, 255, 0.14);
  transform: translateY(-1px);
}

.sidebar-nav-item--active {
  background: var(--sidebar-glass-active);
  border-color: rgba(255, 255, 255, 0.18);
  box-shadow: var(--shadow-neo-sidebar-inset);
  color: white;
}

.active-bar {
  position: absolute;
  left: 0;
  top: 18%;
  bottom: 18%;
  width: 3px;
  border-radius: 9999px;
  background: #d8f255;
  box-shadow: 0 0 8px rgba(216, 242, 85, 0.6);
}

.collapsed-dot {
  position: absolute;
  bottom: 6px;
  left: 50%;
  width: 4px;
  height: 4px;
  border-radius: 9999px;
  background: transparent;
  opacity: 0;
  transform: translateX(-50%) scale(0.65);
  transition:
    background var(--duration-fast) var(--ease-default),
    box-shadow var(--duration-fast) var(--ease-default),
    opacity var(--duration-fast) var(--ease-default),
    transform var(--duration-fast) var(--ease-out-expo);
}

.collapsed-dot--visible {
  opacity: 1;
  transform: translateX(-50%) scale(1);
}

.collapsed-dot--active {
  background: #d8f255;
  box-shadow: 0 0 6px rgba(216, 242, 85, 0.7);
}

.sidebar-user-card {
  background: var(--sidebar-glass-bg);
  backdrop-filter: blur(var(--sidebar-glass-blur));
  -webkit-backdrop-filter: blur(var(--sidebar-glass-blur));
  border: 1px solid var(--sidebar-glass-border);
  box-shadow: var(--shadow-neo-sidebar);
  border-radius: var(--radius-xl);
}

.sidebar-avatar-wrap {
  padding: 2px;
  border-radius: 9999px;
}

.sidebar-avatar {
  background: linear-gradient(135deg, var(--color-primary-400) 0%, var(--color-primary-600) 100%);
  box-shadow:
    0 2px 8px rgba(0, 0, 0, 0.2),
    0 0 0 2px rgba(255, 255, 255, 0.15);
  width: 100%;
  height: 100%;
}

.sidebar-logout-btn {
  background: transparent;
  box-shadow: none;
  border: 1px solid transparent;
}

.sidebar-logout-btn:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.08);
  box-shadow: none;
}

@media (prefers-reduced-motion: reduce) {
  .sidebar,
  .sidebar-collapse-toggle,
  .sidebar-brand,
  .sidebar-logo,
  .sidebar-monogram,
  .runway-spine,
  .sidebar-nav-item,
  .sidebar-nav-label,
  .sidebar-eyebrow,
  .active-bar,
  .collapsed-dot,
  .sidebar-footer,
  .sidebar-user-card,
  .sidebar-user-details,
  .sidebar-logout-btn,
  .sidebar-logout-label,
  .runway-spine__dot {
    transition: none;
  }
}
</style>
