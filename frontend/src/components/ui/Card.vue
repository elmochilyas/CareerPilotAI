<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    padding?: 'sm' | 'md' | 'lg'
    hover?: boolean
    clickable?: boolean
  }>(),
  { hover: false, clickable: false },
)
</script>

<template>
  <section
    class="scroll-mt-28 rounded-[var(--radius-xl)] bg-[var(--surface-primary)] transition-all"
    :style="{ boxShadow: 'var(--shadow-neo-raised)' }"
    :class="[
      padding === 'sm' ? 'p-4' : padding === 'lg' ? 'p-6 sm:p-8' : 'p-5 sm:p-6',
      hover ? 'hover:shadow-[var(--shadow-neo-raised-lg)]' : '',
      clickable ? 'cursor-pointer select-none active:scale-[0.99]' : '',
    ]"
  >
    <header v-if="title || $slots.header" class="flex items-center justify-between gap-3 pb-4">
      <slot name="header">
        <h2 class="text-[var(--text-base)] font-semibold text-[var(--text-primary)]">
          {{ title }}
        </h2>
      </slot>
      <slot name="actions" />
    </header>
    <div :class="title || $slots.header ? 'pt-4' : ''">
      <slot />
    </div>
  </section>
</template>
