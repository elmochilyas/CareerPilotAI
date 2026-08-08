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
    class="group rounded-xl border border-slate-200 bg-white p-5 transition-all"
    :class="href ? 'hover:border-primary-200 hover:shadow-md cursor-pointer' : ''"
  >
    <div class="flex items-start justify-between">
      <div class="flex size-10 items-center justify-center rounded-lg bg-primary-50">
        <component :is="icon" class="size-5 text-primary-600" aria-hidden="true" />
      </div>
      <div
        v-if="href"
        class="rounded-full bg-slate-100 p-1 opacity-0 transition-opacity group-hover:opacity-100"
      >
        <svg class="size-3.5 text-slate-500" viewBox="0 0 16 16" fill="none">
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
      <p class="text-2xl font-bold tracking-tight text-slate-900">{{ value }}</p>
      <p class="mt-0.5 text-sm text-slate-500">{{ label }}</p>
    </div>
  </component>
</template>
