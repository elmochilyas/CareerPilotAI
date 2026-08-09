<script setup lang="ts">
import { CheckCircle, AlertCircle, AlertTriangle, Info, X } from '@lucide/vue'
import { useToast } from '@/composables/useToast'
import type { ToastVariant } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const variantStyles: Record<ToastVariant, { icon: typeof CheckCircle; color: string }> = {
  success: { icon: CheckCircle, color: 'text-emerald-500' },
  error: { icon: AlertCircle, color: 'text-red-500' },
  warning: { icon: AlertTriangle, color: 'text-amber-500' },
  info: { icon: Info, color: 'text-blue-500' },
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed right-4 top-20 z-[400] flex max-w-sm flex-col gap-3" aria-live="polite">
      <TransitionGroup
        enter-active-class="animate-[ds-toast-in_0.35s_cubic-bezier(0.16,1,0.3,1)_both]"
        leave-active-class="animate-[ds-toast-out_0.25s_cubic-bezier(0.55,0,1,0.45)_both]"
        move-class="transition-all duration-300"
      >
        <div
          v-for="t in toasts"
          :key="t.id"
          class="w-full rounded-xl bg-[var(--surface-primary)] px-4 py-3 shadow-[var(--shadow-neo-raised-lg)]"
          role="status"
        >
          <div class="flex items-start gap-3">
            <component
              :is="variantStyles[t.variant].icon"
              :size="18"
              class="mt-0.5 shrink-0"
              :class="variantStyles[t.variant].color"
            />
            <p class="flex-1 text-sm text-[var(--text-primary)]">{{ t.message }}</p>
            <button
              type="button"
              class="ml-1 shrink-0 text-[var(--text-muted)] hover:text-[var(--text-secondary)]"
              aria-label="Dismiss"
              @click="dismiss(t.id)"
            >
              <X :size="14" stroke-width="2" />
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
