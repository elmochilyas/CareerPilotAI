<script setup lang="ts">
import { CheckCircle, AlertCircle, X } from '@lucide/vue'
import { ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    message: string
    variant?: 'success' | 'error'
    duration?: number
    visible?: boolean
  }>(),
  { variant: 'success', duration: 3500, visible: true },
)

const emit = defineEmits<{
  dismiss: []
  close: []
}>()

const _visible = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.message,
  (msg) => {
    if (!msg) return
    _visible.value = true
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
      _visible.value = false
      setTimeout(() => emit('dismiss'), 250)
    }, props.duration)
  },
)

function dismiss() {
  _visible.value = false
  emit('close')
  setTimeout(() => emit('dismiss'), 250)
}
</script>

<template>
  <Teleport to="body">
    <Transition name="toast">
      <div
        v-if="(_visible || visible) && message"
        class="fixed right-4 top-20 z-50 max-w-sm rounded-lg border bg-white px-4 py-3 shadow-lg ring-1 ring-slate-900/5"
        role="status"
      >
        <div class="flex items-start gap-3">
          <CheckCircle
            v-if="variant === 'success'"
            :size="18"
            class="mt-0.5 shrink-0 text-emerald-500"
          />
          <AlertCircle v-else :size="18" class="mt-0.5 shrink-0 text-red-500" />
          <p class="text-sm text-slate-800">{{ message }}</p>
          <button
            type="button"
            class="ml-auto shrink-0 text-slate-400 hover:text-slate-600"
            aria-label="Dismiss"
            @click="dismiss"
          >
            <X :size="14" stroke-width="2" />
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.toast-enter-active {
  animation: toast-in 0.35s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.toast-leave-active {
  animation: toast-out 0.25s cubic-bezier(0.55, 0, 1, 0.45) both;
}
</style>
