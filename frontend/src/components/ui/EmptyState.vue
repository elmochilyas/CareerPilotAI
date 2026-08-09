<script setup lang="ts">
import type { FunctionalComponent, SVGAttributes } from 'vue'

withDefaults(
  defineProps<{
    icon?: FunctionalComponent<SVGAttributes>
    title: string
    description?: string
    size?: 'sm' | 'md' | 'lg'
  }>(),
  { size: 'md' },
)
</script>

<template>
  <div
    class="flex flex-col items-center text-center"
    :class="size === 'sm' ? 'py-6' : size === 'lg' ? 'py-16' : 'py-12'"
  >
    <div
      v-if="icon"
      class="flex items-center justify-center rounded-full bg-[var(--surface-secondary)] shadow-[var(--shadow-neo-inset)]"
      :class="size === 'sm' ? 'size-9' : size === 'lg' ? 'size-14' : 'size-12'"
    >
      <component
        :is="icon"
        class="text-[var(--color-primary-500)]"
        :class="size === 'sm' ? 'size-4.5' : size === 'lg' ? 'size-7' : 'size-6'"
        aria-hidden="true"
      />
    </div>
    <h3
      class="mt-4 font-semibold text-[var(--text-primary)]"
      :class="size === 'sm' ? 'text-xs' : size === 'lg' ? 'text-base' : 'text-sm'"
    >
      {{ title }}
    </h3>
    <p
      v-if="description"
      class="mt-1.5 max-w-xs text-xs leading-relaxed text-[var(--text-secondary)]"
    >
      {{ description }}
    </p>
    <div class="mt-5">
      <slot />
    </div>
  </div>
</template>
