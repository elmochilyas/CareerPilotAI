<script setup lang="ts">
import Button from './Button.vue'

withDefaults(
  defineProps<{
    submitLabel?: string
    cancelLabel?: string
    busy?: boolean
    busyLabel?: string
  }>(),
  { submitLabel: 'Save', cancelLabel: 'Cancel' },
)

const emit = defineEmits<{
  submit: []
  cancel: []
}>()
</script>

<template>
  <div class="flex items-center justify-end gap-3">
    <Button variant="outline" @click="emit('cancel')">
      {{ cancelLabel }}
    </Button>
    <Button :loading="busy" type="submit" @click="emit('submit')">
      {{ busy ? busyLabel || 'Saving...' : submitLabel }}
    </Button>
  </div>
</template>
