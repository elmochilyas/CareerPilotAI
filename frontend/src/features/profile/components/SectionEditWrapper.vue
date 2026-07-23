<script setup lang="ts">
import { X, Pencil } from '@lucide/vue'
defineProps<{ title: string; editing?: boolean }>()
defineEmits<{ edit: []; cancel: [] }>()
</script>

<template>
  <section
    class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-all duration-300 hover:border-slate-300 hover:shadow-md"
    :aria-labelledby="`${title}-heading`"
  >
    <header
      class="flex items-center justify-between gap-3 border-b border-slate-100/80 bg-gradient-to-b from-slate-50/50 to-white px-5 py-4 sm:px-6"
    >
      <h2 :id="`${title}-heading`" class="text-base font-bold tracking-tight text-slate-950">
        <slot name="heading">{{ title }}</slot>
      </h2>
      <div class="flex items-center gap-2">
        <button
          v-if="editing"
          type="button"
          class="flex min-h-10 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 active:scale-[0.97]"
          @click="$emit('cancel')"
        >
          <X :size="16" stroke-width="1.5" />
          Cancel
        </button>
        <button
          v-else
          type="button"
          class="flex min-h-10 cursor-pointer items-center gap-1.5 rounded-lg bg-gradient-to-r from-primary-50 to-primary-100 px-3 text-sm font-semibold text-primary-700 shadow-sm transition-all hover:from-primary-100 hover:to-primary-200 active:scale-[0.97]"
          @click="$emit('edit')"
        >
          <Pencil :size="16" stroke-width="1.5" />
          Edit
        </button>
      </div>
    </header>
    <div class="p-5 transition-all duration-300 sm:p-6" :class="{ 'opacity-60': editing }">
      <slot />
    </div>
  </section>
</template>
