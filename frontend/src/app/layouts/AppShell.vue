<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { X, Menu } from '@lucide/vue'
import SkipLink from '@/components/ui/SkipLink.vue'
import Sidebar from '@/components/navigation/Sidebar.vue'

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

  <button
    type="button"
    class="appshell-hamburger fixed top-3 left-3 z-[var(--z-sticky)] flex size-9 items-center justify-center rounded-xl bg-[var(--surface-primary)] text-[var(--text-secondary)] transition-colors hover:text-[var(--text-primary)] lg:hidden"
    aria-label="Toggle sidebar"
    @click="toggleSidebar"
  >
    <Menu :size="20" />
  </button>

  <div class="flex">
    <div
      class="hidden lg:block lg:shrink-0 lg:sticky lg:top-0 lg:h-screen lg:relative lg:z-[1]"
      :style="{ width: sidebarWidth }"
    >
      <Sidebar class="h-full" />
    </div>

    <div
      v-if="!collapsed"
      class="hidden md:block lg:hidden md:shrink-0 md:sticky md:top-0 md:h-screen md:relative md:z-[1]"
      :style="{ width: 'var(--sidebar-collapsed-width)' }"
    >
      <Sidebar class="h-full" collapsed />
    </div>

    <Teleport to="body">
      <Transition name="overlay">
        <div
          v-if="sidebarOpen"
          class="fixed inset-0 z-[var(--z-overlay)] bg-[var(--surface-overlay)] backdrop-blur-sm md:hidden"
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
              class="absolute top-3 right-3 flex size-8 items-center justify-center rounded-xl text-white/70 transition-colors hover:bg-white/10 hover:text-white"
              aria-label="Close sidebar"
              @click="closeSidebar"
            >
              <X :size="18" />
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <main :id="mainId" class="flex-1 bg-[var(--surface-page)] relative z-0" tabindex="-1">
      <div
        class="mx-auto px-4 py-6 sm:px-6 lg:px-8"
        :style="{ maxWidth: 'var(--content-max-width)' }"
      >
        <router-view v-slot="{ Component }">
          <Transition name="ds-page" mode="out-in">
            <Suspense>
              <component :is="Component" />
              <template #fallback>
                <div
                  class="flex items-center justify-center py-32"
                  role="status"
                  aria-live="polite"
                >
                  <div
                    class="size-8 animate-spin rounded-full border-4 border-[var(--color-neutral-200)] border-t-[var(--color-primary-600)]"
                  />
                </div>
              </template>
            </Suspense>
          </Transition>
        </router-view>
      </div>
    </main>
  </div>
</template>

<style scoped>
.appshell-hamburger {
  box-shadow: var(--shadow-neo-raised-sm);
}

.overlay-enter-active,
.overlay-leave-active {
  transition: opacity var(--duration-normal) var(--ease-out-expo);
}
.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}

.drawer-enter-active,
.drawer-leave-active {
  transition: transform var(--duration-slow) var(--ease-out-expo);
}
.drawer-enter-from,
.drawer-leave-to {
  transform: translateX(-100%);
}
</style>
