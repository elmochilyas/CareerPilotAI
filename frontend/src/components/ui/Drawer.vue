<script setup lang="ts">
import { X } from '@lucide/vue'
import { nextTick, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    size?: 'sm' | 'md' | 'lg'
    closeable?: boolean
  }>(),
  { size: 'md', closeable: true },
)

const emit = defineEmits<{
  close: []
}>()

const panelRef = ref<HTMLDivElement | null>(null)
const previousFocus = ref<HTMLElement | null>(null)

const sizeClass: Record<string, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-2xl',
}

watch(
  () => props.open,
  async (v) => {
    if (v) {
      previousFocus.value = document.activeElement as HTMLElement
      document.body.style.overflow = 'hidden'
      await nextTick()
      panelRef.value?.focus()
    } else {
      document.body.style.overflow = ''
      if (previousFocus.value) {
        previousFocus.value.focus()
      }
    }
  },
)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.closeable) {
    emit('close')
  }
  if (e.key === 'Tab') {
    const root = e.currentTarget as HTMLElement
    const focusable = [
      ...root.querySelectorAll<HTMLElement>(
        'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    ]
    if (focusable.length === 0) return
    const index = focusable.indexOf(document.activeElement as HTMLElement)
    e.preventDefault()
    const next = (index + (e.shiftKey ? -1 : 1) + focusable.length) % focusable.length
    focusable[next]?.focus()
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer-backdrop">
      <div
        v-if="open"
        class="fixed inset-0 bg-slate-950/40"
        style="z-index: var(--z-modal)"
        @click="closeable ? emit('close') : undefined"
      />
    </Transition>
    <Transition name="drawer-panel">
      <div
        v-if="open"
        ref="panelRef"
        class="fixed inset-y-0 right-0 flex flex-col overflow-hidden bg-white shadow-xl"
        :class="sizeClass[size]"
        style="z-index: var(--z-modal)"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        tabindex="-1"
        @keydown="onKeydown"
      >
        <div
          v-if="title || closeable"
          class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
        >
          <h2 class="text-lg font-semibold text-slate-900">
            {{ title }}
          </h2>
          <button
            v-if="closeable"
            type="button"
            class="flex size-8 items-center justify-center rounded-lg text-slate-400 transition-all hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            aria-label="Close"
            @click="emit('close')"
          >
            <X :size="16" stroke-width="2" />
          </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4">
          <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-slate-100 px-6 py-4">
          <slot name="footer" />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-backdrop-enter-active {
  transition: opacity var(--duration-normal) var(--ease-out);
}
.drawer-backdrop-leave-active {
  transition: opacity var(--duration-fast) var(--ease-in);
}
.drawer-backdrop-enter-from,
.drawer-backdrop-leave-to {
  opacity: 0;
}

.drawer-panel-enter-active {
  transition: transform var(--duration-slow) var(--ease-default);
}
.drawer-panel-leave-active {
  transition: transform var(--duration-normal) var(--ease-in);
}
.drawer-panel-enter-from,
.drawer-panel-leave-to {
  transform: translateX(100%);
}
</style>
