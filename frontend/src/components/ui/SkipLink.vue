<script setup lang="ts">
const mainId = 'main-content'
</script>

<template>
  <a
    :href="`#${mainId}`"
    class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-primary-600 focus:px-4 focus:py-2 focus:text-white"
  >
    Skip to content
  </a>
</template>
