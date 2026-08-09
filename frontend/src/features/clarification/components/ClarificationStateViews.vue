<script setup lang="ts">
import { AlertTriangle, CheckCircle2, HelpCircle } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'

withDefaults(
  defineProps<{
    kind: 'loading' | 'error' | 'empty' | 'expired' | 'success'
    errorDetail?: string | null
  }>(),
  { errorDetail: null },
)

const emit = defineEmits<{ retry: []; restart: []; generate: []; close: [] }>()
</script>

<template>
  <div v-if="kind === 'loading'" class="grid gap-4 py-2" role="status">
    <div class="grid gap-3">
      <Skeleton classes="h-4 w-3/4" />
      <Skeleton classes="h-24 w-full" />
      <Skeleton classes="h-4 w-1/2" />
    </div>
    <div class="flex justify-end gap-2">
      <Skeleton classes="h-10 w-24" />
      <Skeleton classes="h-10 w-28" />
    </div>
    <span class="sr-only">Loading clarification questions…</span>
  </div>

  <div
    v-else-if="kind === 'error'"
    class="grid justify-items-center gap-3 py-6 text-center"
    role="alert"
  >
    <span
      class="flex size-12 items-center justify-center rounded-full bg-red-50 text-red-600 shadow-[var(--shadow-neo-raised)]"
    >
      <AlertTriangle class="size-6" aria-hidden="true" />
    </span>
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Something went wrong</h3>
      <p class="mt-1 text-sm text-slate-600">
        {{ errorDetail ?? 'The clarification questions could not be loaded.' }}
      </p>
    </div>
    <Button variant="outline" size="sm" @click="emit('retry')">Retry</Button>
  </div>

  <div v-else-if="kind === 'empty'" class="grid justify-items-center gap-3 py-6 text-center">
    <span
      class="flex size-12 items-center justify-center rounded-full bg-[var(--surface-secondary)] text-[var(--text-muted)] shadow-[var(--shadow-neo-raised)]"
    >
      <HelpCircle class="size-6" aria-hidden="true" />
    </span>
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Nothing to clarify</h3>
      <p class="mt-1 text-sm text-slate-600">
        There are no open clarification questions right now. Generate a fresh set from the latest
        analysis.
      </p>
    </div>
    <div class="flex flex-wrap items-center justify-center gap-2">
      <Button variant="primary" size="sm" @click="emit('generate')">Generate questions</Button>
      <Button variant="secondary" size="sm" @click="emit('close')">Done</Button>
    </div>
  </div>

  <div
    v-else-if="kind === 'expired'"
    class="grid justify-items-center gap-3 py-6 text-center"
    role="alert"
  >
    <span
      class="flex size-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 shadow-[var(--shadow-neo-raised)]"
    >
      <HelpCircle class="size-6" aria-hidden="true" />
    </span>
    <div>
      <h3 class="text-sm font-semibold text-slate-900">This session expired</h3>
      <p class="mt-1 text-sm text-slate-600">Start a fresh clarification session to continue.</p>
    </div>
    <Button variant="primary" size="sm" @click="emit('restart')">Start a fresh session</Button>
  </div>

  <div v-else class="grid justify-items-center gap-3 py-6 text-center">
    <span
      class="flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 shadow-[var(--shadow-neo-raised)]"
    >
      <CheckCircle2 class="size-6" aria-hidden="true" />
    </span>
    <div>
      <h3 class="text-sm font-semibold text-slate-900">You're all caught up</h3>
      <p class="mt-1 text-sm text-slate-600">
        Your profile was updated. The match is now stale and can be recalculated.
      </p>
    </div>
    <Button variant="secondary" size="sm" @click="emit('close')">Done</Button>
  </div>
</template>
