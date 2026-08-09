<script setup lang="ts">
import { useRoute } from 'vue-router'
import { Menu, Bell } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'

defineProps<{
  sidebarOpen: boolean
}>()

defineEmits<{
  toggleSidebar: []
}>()

const route = useRoute()
const auth = useAuthStore()

const breadcrumbs: Record<string, string> = {
  home: 'Dashboard',
  profile: 'Profile',
  'cv-ingestion': 'CV',
  opportunities: 'Opportunities',
  'opportunities-import': 'Import Job',
  'opportunities-detail': 'Opportunity',
  'opportunities-match': 'Match',
  'opportunities-match-clarifications': 'Clarifications',
}
</script>

<template>
  <header
    class="topbar fixed top-0 right-0 z-[var(--z-sticky)] flex h-[var(--topbar-height)] items-center bg-[var(--surface-primary)] px-4"
    style="left: 0"
    role="banner"
  >
    <button
      type="button"
      class="topbar-btn mr-3 flex size-8 items-center justify-center rounded-xl bg-[var(--surface-primary)] text-[var(--text-secondary)] transition-colors hover:text-[var(--text-primary)] lg:hidden"
      aria-label="Toggle sidebar"
      @click="$emit('toggleSidebar')"
    >
      <Menu :size="20" />
    </button>

    <div class="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
      <span class="font-medium text-[var(--text-primary)]">
        {{ breadcrumbs[route.name as string] ?? (route.meta?.title as string) ?? '' }}
      </span>
    </div>

    <div class="ml-auto flex items-center gap-3">
      <button
        type="button"
        class="topbar-btn topbar-bell relative flex size-8 items-center justify-center rounded-full bg-[var(--surface-secondary)] text-[var(--text-secondary)] transition-colors duration-[120ms] hover:text-[var(--text-primary)]"
        aria-label="Notifications"
      >
        <Bell :size="18" />
      </button>

      <div class="hidden items-center gap-2 sm:flex">
        <span class="text-sm font-medium text-[var(--text-primary)]">
          {{ auth.user?.full_name }}
        </span>
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  box-shadow: 0 4px 12px rgba(203, 198, 208, 0.4);
}

.topbar-btn {
  box-shadow: var(--shadow-neo-raised-sm);
}

.topbar-bell {
  box-shadow: var(--shadow-neo-raised-sm);
}
</style>
