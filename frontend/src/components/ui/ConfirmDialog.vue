<script setup lang="ts">
import { Trash2 } from '@lucide/vue'
import Modal from './Modal.vue'
import Button from './Button.vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    description?: string
    busy?: boolean
    confirmLabel?: string
    busyLabel?: string
    cancelLabel?: string
  }>(),
  {
    title: 'Delete this item?',
    description: 'This action cannot be undone. Are you sure you want to delete this item?',
    busy: false,
    confirmLabel: 'Delete',
    busyLabel: 'Deleting…',
    cancelLabel: 'Cancel',
  },
)

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<template>
  <Modal :open="open" :title="props.title" size="sm" @close="emit('cancel')">
    <div class="p-6">
      <div class="flex items-start gap-4">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100">
          <Trash2 :size="18" class="text-red-600" stroke-width="1.5" aria-hidden="true" />
        </div>
        <div>
          <p class="text-sm text-slate-600">
            {{ props.description }}
          </p>
        </div>
      </div>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-4">
      <Button variant="outline" :disabled="props.busy" @click="emit('cancel')">
        {{ props.cancelLabel }}
      </Button>
      <Button variant="danger" :disabled="props.busy" @click="emit('confirm')">
        {{ props.busy ? props.busyLabel : props.confirmLabel }}
      </Button>
    </div>
  </Modal>
</template>
