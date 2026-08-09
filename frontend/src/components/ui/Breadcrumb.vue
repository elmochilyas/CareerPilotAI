<script setup lang="ts">
import { ChevronRight } from '@lucide/vue'
import { RouterLink } from 'vue-router'

defineProps<{
  items: Array<{ label: string; to?: string | object; active?: boolean }>
}>()
</script>

<template>
  <nav aria-label="Breadcrumb" class="flex items-center">
    <ol class="flex flex-wrap items-center gap-1">
      <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1">
        <ChevronRight
          v-if="index > 0"
          class="size-3.5 text-[var(--text-muted)]"
          aria-hidden="true"
        />
        <component
          :is="item.to && !item.active ? RouterLink : 'span'"
          :to="item.to"
          class="text-[var(--text-sm)] transition-all duration-200 rounded-lg px-1.5 py-0.5"
          :class="
            item.active || !item.to
              ? 'font-medium text-[var(--text-primary)] cursor-default'
              : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface-secondary)] hover:shadow-[var(--shadow-neo-raised-sm)]'
          "
          :aria-current="item.active ? 'page' : undefined"
        >
          {{ item.label }}
        </component>
      </li>
    </ol>
  </nav>
</template>
