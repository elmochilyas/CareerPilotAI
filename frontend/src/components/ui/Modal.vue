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
  { closeable: true },
)

const emit = defineEmits<{
  close: []
}>()

const dialogRef = ref<HTMLDivElement | null>(null)
const previousFocus = ref<HTMLElement | null>(null)

watch(
  () => props.open,
  async (v) => {
    if (v) {
      previousFocus.value = document.activeElement as HTMLElement
      await nextTick()
      dialogRef.value?.focus()
    } else if (previousFocus.value) {
      previousFocus.value.focus()
    }
  },
)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.closeable) emit('close')
  if (e.key === 'Tab') {
    const root = e.currentTarget as HTMLElement
    const buttons = [
      ...root.querySelectorAll<HTMLElement>(
        'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    ]
    if (buttons.length === 0) return
    const index = buttons.indexOf(document.activeElement as HTMLElement)
    e.preventDefault()
    const next = (index + (e.shiftKey ? -1 : 1) + buttons.length) % buttons.length
    buttons[next]?.focus()
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        ref="dialogRef"
        class="fixed inset-0 z-[300] grid place-items-center overflow-y-auto p-4"
        :style="{ backgroundColor: 'var(--surface-overlay)' }"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        tabindex="-1"
        @keydown="onKeydown"
        @click.self="closeable && emit('close')"
      >
        <div
          class="my-4 w-full rounded-lg bg-white shadow-xl ring-1 ring-slate-900/5"
          :class="size === 'sm' ? 'max-w-md' : size === 'lg' ? 'max-w-2xl' : 'max-w-xl'"
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
              class="flex size-8 items-center justify-center rounded-lg text-slate-400 transition-all hover:bg-slate-100 hover:text-slate-600"
              aria-label="Close"
              @click="emit('close')"
            >
              <X :size="16" stroke-width="2" />
            </button>
          </div>
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active {
  transition: opacity var(--duration-normal) var(--ease-out);
}
.modal-enter-active > div {
  transition:
    transform var(--duration-normal) var(--ease-spring),
    opacity var(--duration-normal) var(--ease-out);
}
.modal-leave-active {
  transition: opacity var(--duration-fast) var(--ease-in);
}
.modal-leave-active > div {
  transition:
    transform var(--duration-fast) var(--ease-in),
    opacity var(--duration-fast) var(--ease-in);
}
.modal-enter-from {
  opacity: 0;
}
.modal-enter-from > div {
  transform: scale(0.95) translateY(8px);
  opacity: 0;
}
.modal-leave-to {
  opacity: 0;
}
.modal-leave-to > div {
  transform: scale(0.95);
  opacity: 0;
}
</style>
