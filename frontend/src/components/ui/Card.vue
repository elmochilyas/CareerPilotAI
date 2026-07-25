<script setup lang="ts">
defineProps<{
  title?: string
  padding?: 'sm' | 'md' | 'lg'
}>()
</script>

<template>
  <section
    class="scroll-mt-28 rounded-lg border border-slate-200 bg-white shadow-sm"
    :class="padding === 'sm' ? 'p-4' : padding === 'lg' ? 'p-6 sm:p-8' : 'p-5 sm:p-6'"
  >
    <header
      v-if="title || $slots.header"
      class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4"
    >
      <slot name="header">
        <h2 class="text-base font-semibold text-slate-900">
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
