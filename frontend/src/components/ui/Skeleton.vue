<script setup lang="ts">
withDefaults(
  defineProps<{
    classes?: string
  }>(),
  { classes: '' },
)
</script>

<template>
  <div class="ds-animate-shimmer rounded-lg" :class="classes" />
</template>
