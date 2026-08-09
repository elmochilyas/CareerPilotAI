<script setup lang="ts">
withDefaults(
  defineProps<{
    classes?: string
  }>(),
  { classes: '' },
)
</script>

<template>
  <div class="ds-animate-shimmer rounded-xl" :class="classes" />
</template>
