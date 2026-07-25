<script setup lang="ts">
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

defineProps<{ open: boolean; busy?: boolean }>()
const emit = defineEmits<{ confirm: []; cancel: [] }>()
</script>

<template>
  <ConfirmDialog
    :open="open"
    :busy="busy"
    title="Delete this item?"
    description="This action cannot be undone. Are you sure you want to delete this profile item?"
    @confirm="emit('confirm')"
    @cancel="emit('cancel')"
  />
</template>
