<script setup lang="ts">
import type { FunctionalComponent, SVGAttributes } from 'vue'

defineProps<{
  label: string
  value: string | number
  icon: FunctionalComponent<SVGAttributes>
  href?: string
}>()
</script>

<template>
  <component
    :is="href ? 'router-link' : 'div'"
    v-bind="href ? { to: href } : {}"
    class="group rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5 transition-all duration-[120ms]"
    :class="
      href
        ? 'cursor-pointer shadow-[var(--shadow-neo-raised)] hover:shadow-[var(--shadow-neo-raised-lg)]'
        : 'shadow-[var(--shadow-neo-raised)]'
    "
  >
    <div class="flex items-start justify-between">
      <div
        class="flex size-10 items-center justify-center rounded-[var(--radius-lg)] bg-[var(--color-primary-50)] shadow-[var(--shadow-neo-inset)]"
      >
        <component :is="icon" class="size-5 text-[var(--color-primary-600)]" aria-hidden="true" />
      </div>
      <div
        v-if="href"
        class="rounded-full bg-[var(--color-neutral-100)] p-1 opacity-0 transition-opacity group-hover:opacity-100"
      >
        <svg class="size-3.5 text-[var(--text-muted)]" viewBox="0 0 16 16" fill="none">
          <path
            d="M6 4l4 4-4 4"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
    </div>
    <div class="mt-4">
      <p class="text-[var(--text-2xl)] font-bold tracking-tight text-[var(--text-primary)]">
        {{ value }}
      </p>
      <p class="mt-0.5 text-[var(--text-sm)] text-[var(--text-tertiary)]">{{ label }}</p>
    </div>
  </component>
</template>
