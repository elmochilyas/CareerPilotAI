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
        <ChevronRight v-if="index > 0" class="size-3.5 text-slate-400" aria-hidden="true" />
        <component
          :is="item.to && !item.active ? RouterLink : 'span'"
          :to="item.to"
          class="text-sm transition-colors"
          :class="
            item.active || !item.to
              ? 'font-medium text-slate-900 cursor-default'
              : 'text-slate-500 hover:text-slate-700'
          "
          :aria-current="item.active ? 'page' : undefined"
        >
          {{ item.label }}
        </component>
      </li>
    </ol>
  </nav>
</template>
