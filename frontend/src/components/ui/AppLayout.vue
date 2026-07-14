<script setup lang="ts">
import SkipLink from './SkipLink.vue'
import NavBar from './NavBar.vue'

const mainId = 'main-content'
</script>

<template>
  <SkipLink />
  <NavBar />
  <main
    :id="mainId"
    class="mx-auto min-h-[calc(100vh-4rem)] max-w-7xl px-4 py-6 sm:px-6 lg:px-8"
    tabindex="-1"
  >
    <slot />
  </main>
</template>
