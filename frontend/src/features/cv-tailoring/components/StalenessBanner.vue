<script setup lang="ts">
defineProps<{
  reason?: string | null
}>()

const emit = defineEmits<{
  retailor: []
}>()
</script>

<template>
  <div
    role="status"
    class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-[var(--color-warning-50)] px-4 py-3 shadow-[var(--shadow-neo-raised-sm)]"
  >
    <div class="min-w-0">
      <p class="text-sm font-semibold text-[var(--color-warning-800)]">
        Profile or opportunity changed since this resume was generated
      </p>
      <p v-if="reason" class="mt-0.5 text-xs text-[var(--color-warning-700)]">{{ reason }}</p>
      <p v-else class="mt-0.5 text-xs text-[var(--color-warning-700)]">
        Re-tailor to see an up-to-date resume. The previous version stays visible below.
      </p>
    </div>
    <button
      class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[var(--color-warning-600)] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-[var(--color-warning-700)] active:scale-[0.97]"
      @click="emit('retailor')"
    >
      <svg
        class="size-3.5"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M1 4v6h6M23 20v-6h-6" />
        <path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15" />
      </svg>
      Re-tailor
    </button>
  </div>
</template>
