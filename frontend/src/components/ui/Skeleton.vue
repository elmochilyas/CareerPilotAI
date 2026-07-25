<script setup lang="ts">
withDefaults(
  defineProps<{
    classes?: string
  }>(),
  { classes: '' },
)
</script>

<template>
  <div class="animate-shimmer rounded-lg bg-slate-200/60" :class="classes" />
</template>
