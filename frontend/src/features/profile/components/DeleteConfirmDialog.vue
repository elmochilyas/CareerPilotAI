<script setup lang="ts">
import { Trash2 } from '@lucide/vue'
import { nextTick, ref, watch } from 'vue'

const props = defineProps<{ open: boolean; busy?: boolean }>()
const emit = defineEmits<{ confirm: []; cancel: [] }>()

const cancelButton = ref<HTMLButtonElement | null>(null)

watch(
  () => props.open,
  async (v) => {
    if (v) {
      await nextTick()
      cancelButton.value?.focus()
    }
  },
)

function trap(event: KeyboardEvent) {
  if (event.key === 'Escape') emit('cancel')
  if (event.key === 'Tab') {
    const root = event.currentTarget as HTMLElement
    const buttons = [...root.querySelectorAll<HTMLButtonElement>('button:not([disabled])')]
    if (buttons.length === 0) return
    const index = buttons.indexOf(document.activeElement as HTMLButtonElement)
    event.preventDefault()
    buttons[(index + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length]?.focus()
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-title"
        @keydown="trap"
      >
        <div
          class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-900/15 ring-1 ring-slate-900/5"
        >
          <div class="p-6">
            <div class="flex items-start gap-4">
              <div
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-red-100 to-red-200 shadow-inner"
              >
                <Trash2 :size="20" class="text-red-600" stroke-width="1.5" />
              </div>
              <div class="min-w-0">
                <h2 id="delete-title" class="text-lg font-bold text-slate-900">
                  Delete this item?
                </h2>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600">
                  This action cannot be undone. Are you sure you want to delete this profile item?
                </p>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100/80 bg-slate-50/50 px-6 py-4">
            <button
              ref="cancelButton"
              type="button"
              class="min-h-11 cursor-pointer rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm transition-all hover:bg-slate-50 active:scale-[0.97]"
              @click="$emit('cancel')"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="busy"
              class="min-h-11 cursor-pointer rounded-xl bg-gradient-to-br from-red-600 to-red-500 px-4 text-sm font-semibold text-white shadow-md shadow-red-500/20 transition-all hover:shadow-lg hover:shadow-red-500/30 active:scale-[0.97] disabled:opacity-60"
              @click="$emit('confirm')"
            >
              {{ busy ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active {
  transition: opacity 0.2s ease-out;
}
.modal-enter-active > div {
  transition:
    transform 0.2s cubic-bezier(0.16, 1, 0.3, 1),
    opacity 0.2s ease-out;
}
.modal-leave-active {
  transition: opacity 0.15s ease-in;
}
.modal-leave-active > div {
  transition:
    transform 0.15s ease-in,
    opacity 0.15s ease-in;
}
.modal-enter-from {
  opacity: 0;
}
.modal-enter-from > div {
  transform: scale(0.92) translateY(8px);
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
