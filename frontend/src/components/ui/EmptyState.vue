<script setup lang="ts">
import type { FunctionalComponent, SVGAttributes } from 'vue'

defineProps<{
  icon?: FunctionalComponent<SVGAttributes>
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center py-12 text-center">
    <div v-if="icon" class="flex size-12 items-center justify-center rounded-full bg-slate-100">
      <component :is="icon" class="size-6 text-slate-400" aria-hidden="true" />
    </div>
    <h3 class="mt-4 text-sm font-semibold text-slate-900">
      {{ title }}
    </h3>
    <p v-if="description" class="mt-1.5 max-w-xs text-xs leading-relaxed text-slate-500">
      {{ description }}
    </p>
    <div class="mt-5">
      <slot />
    </div>
  </div>
</template>
