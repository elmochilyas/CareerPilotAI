<script setup lang="ts">
import { X, Pencil } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'

defineProps<{ title: string; editing?: boolean }>()
defineEmits<{ edit: []; cancel: [] }>()
</script>

<template>
  <section :aria-labelledby="`${title}-heading`">
    <header class="flex items-center justify-between gap-3 border-b border-slate-100 py-4">
      <h2 :id="`${title}-heading`" class="text-base font-semibold text-slate-900">
        <slot name="heading">{{ title }}</slot>
      </h2>
      <div class="flex items-center gap-2">
        <Button v-if="editing" variant="outline" size="sm" @click="$emit('cancel')">
          <X :size="14" />
          Cancel
        </Button>
        <button
          v-else
          type="button"
          class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-500 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 active:scale-[0.97]"
          @click="$emit('edit')"
        >
          <Pencil :size="13" />
          Edit
        </button>
      </div>
    </header>
    <div :class="{ 'opacity-60 pointer-events-none': editing }">
      <slot />
    </div>
  </section>
</template>
