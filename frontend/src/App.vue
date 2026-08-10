<script setup lang="ts">
import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { initialized, isAuthenticated } = storeToRefs(auth)

watch(
  [initialized, isAuthenticated],
  ([isInitialized, hasSession]) => {
    if (!isInitialized || hasSession || !route.meta.requiresAuth || route.name === 'login') {
      return
    }

    const redirect = route.fullPath.replace(/^\/{2,}/, '/')
    void router.replace({ name: 'login', query: { redirect } })
  },
  { immediate: true },
)
</script>

<template>
  <router-view />
</template>
