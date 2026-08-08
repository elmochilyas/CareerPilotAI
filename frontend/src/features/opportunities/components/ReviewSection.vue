<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <section class="grid gap-3" :aria-labelledby="`section-${title}`">
    <div>
      <h2 :id="`section-${title}`" class="text-lg font-semibold text-slate-900">
        {{ title }}
      </h2>
      <p v-if="description" class="mt-0.5 text-sm text-slate-500">
        {{ description }}
      </p>
    </div>
    <div class="grid gap-2">
      <slot />
    </div>
    <slot name="footer" />
  </section>
</template>
