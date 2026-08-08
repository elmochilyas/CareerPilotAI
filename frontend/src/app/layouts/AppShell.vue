<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { X } from '@lucide/vue'
import SkipLink from '@/components/ui/SkipLink.vue'
import Sidebar from '@/components/navigation/Sidebar.vue'
import TopBar from '@/components/navigation/TopBar.vue'

const mainId = 'main-content'

const sidebarOpen = ref(false)
const collapsed = ref(false)

function handleResize(): void {
  const w = window.innerWidth
  if (w >= 1024) {
    sidebarOpen.value = false
    collapsed.value = false
  } else if (w >= 768) {
    sidebarOpen.value = false
    collapsed.value = true
  } else {
    collapsed.value = false
  }
}

function toggleSidebar(): void {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebar(): void {
  sidebarOpen.value = false
}

onMounted(() => {
  handleResize()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

const sidebarWidth = collapsed.value ? 'var(--sidebar-collapsed-width)' : 'var(--sidebar-width)'
</script>

<template>
  <SkipLink />
  <TopBar :sidebar-open="sidebarOpen" @toggle-sidebar="toggleSidebar" />

  <div class="flex h-screen pt-[var(--topbar-height)]">
    <div class="hidden lg:block lg:shrink-0" :style="{ width: sidebarWidth }">
      <Sidebar class="h-full" />
    </div>

    <div
      v-if="!collapsed"
      class="hidden md:block lg:hidden md:shrink-0"
      :style="{ width: 'var(--sidebar-collapsed-width)' }"
    >
      <Sidebar class="h-full" collapsed />
    </div>

    <Teleport to="body">
      <Transition name="overlay">
        <div
          v-if="sidebarOpen"
          class="fixed inset-0 z-[var(--z-overlay)] bg-[var(--surface-overlay)] md:hidden"
          @click="closeSidebar"
        />
      </Transition>
      <Transition name="drawer">
        <div
          v-if="sidebarOpen"
          class="fixed inset-y-0 left-0 z-[var(--z-overlay)] w-[var(--sidebar-width)] md:hidden"
        >
          <div class="relative flex h-full">
            <Sidebar class="h-full" />
            <button
              type="button"
              class="absolute top-3 right-3 flex size-8 items-center justify-center rounded-lg text-[var(--text-secondary)] transition-colors hover:bg-[var(--surface-secondary)]"
              aria-label="Close sidebar"
              @click="closeSidebar"
            >
              <X :size="18" />
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <main :id="mainId" class="flex-1 overflow-y-auto bg-[var(--surface-page)]" tabindex="-1">
      <div
        class="mx-auto px-4 py-6 sm:px-6 lg:px-8"
        :style="{ maxWidth: 'var(--content-max-width)' }"
      >
        <router-view />
      </div>
    </main>
  </div>
</template>

<style scoped>
.overlay-enter-active,
.overlay-leave-active {
  transition: opacity var(--duration-normal) var(--ease-default);
}
.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}

.drawer-enter-active,
.drawer-leave-active {
  transition: transform var(--duration-normal) var(--ease-default);
}
.drawer-enter-from,
.drawer-leave-to {
  transform: translateX(-100%);
}
</style>
