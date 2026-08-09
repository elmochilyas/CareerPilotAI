<script setup lang="ts">
import { X, Pencil } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'

defineProps<{ title: string; editing?: boolean }>()
defineEmits<{ edit: []; cancel: [] }>()
</script>

<template>
  <section :aria-labelledby="`${title}-heading`">
    <header class="flex items-center justify-between gap-3 py-4">
      <h2
        :id="`${title}-heading`"
        class="text-[var(--text-base)] font-semibold text-[var(--text-primary)]"
      >
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
          class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-[var(--radius-lg)] bg-[var(--surface-primary)] px-3 text-xs font-medium text-[var(--text-muted)] shadow-[var(--shadow-neo-raised-sm)] transition-all hover:shadow-[var(--shadow-neo-raised)] hover:text-[var(--text-secondary)] active:shadow-[var(--shadow-neo-button-pressed)] active:scale-[0.97]"
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
