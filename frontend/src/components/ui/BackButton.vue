<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'

withDefaults(
  defineProps<{
    /** Target route. When omitted, renders a <button> that emits `click`. */
    to?: RouteLocationRaw
    label?: string
  }>(),
  {
    label: 'Back',
  },
)

const emit = defineEmits<{
  click: []
}>()
</script>

<template>
  <RouterLink
    v-if="to"
    :to="to"
    class="inline-flex items-center gap-1.5 rounded-[var(--radius-md)] px-3 py-1.5 text-sm font-medium text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)] transition-all hover:text-[var(--color-primary-800)] hover:shadow-[var(--shadow-neo-button)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
    @click="emit('click')"
  >
    <ArrowLeft class="size-4 shrink-0" aria-hidden="true" />
    <slot>{{ label }}</slot>
  </RouterLink>
  <button
    v-else
    type="button"
    class="inline-flex items-center gap-1.5 rounded-[var(--radius-md)] px-3 py-1.5 text-sm font-medium text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)] transition-all hover:text-[var(--color-primary-800)] hover:shadow-[var(--shadow-neo-button)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
    @click="emit('click')"
  >
    <ArrowLeft class="size-4 shrink-0" aria-hidden="true" />
    <slot>{{ label }}</slot>
  </button>
</template>
