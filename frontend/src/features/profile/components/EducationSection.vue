<script setup lang="ts">
import type { ProfileItem, ProfileItemInput } from '../types'
import ProfileItemsSection from './ProfileItemsSection.vue'
defineProps<{ items: ProfileItem[]; busy?: boolean }>()
defineEmits<{
  create: [v: ProfileItemInput]
  update: [i: ProfileItem, v: ProfileItemInput]
  delete: [i: ProfileItem]
  reorder: [ids: number[]]
  dirty: [v: boolean]
}>()
</script>
<template>
  <ProfileItemsSection
    title="Education"
    type="education"
    variant="academic"
    v-bind="$props"
    @create="$emit('create', $event)"
    @update="(i, v) => $emit('update', i, v)"
    @delete="$emit('delete', $event)"
    @reorder="$emit('reorder', $event)"
    @dirty="$emit('dirty', $event)"
  />
</template>
