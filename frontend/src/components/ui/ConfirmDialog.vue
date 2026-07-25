<script setup lang="ts">
import { Trash2 } from '@lucide/vue'
import Modal from './Modal.vue'
import Button from './Button.vue'

defineProps<{
  open: boolean
  title?: string
  description?: string
  busy?: boolean
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<template>
  <Modal :open="open" title="Delete this item?" size="sm" @close="emit('cancel')">
    <div class="p-6">
      <div class="flex items-start gap-4">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100">
          <Trash2 :size="18" class="text-red-600" stroke-width="1.5" />
        </div>
        <div>
          <p class="text-sm text-slate-600">
            {{
              description ||
              'This action cannot be undone. Are you sure you want to delete this item?'
            }}
          </p>
        </div>
      </div>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-4">
      <Button variant="outline" @click="emit('cancel')"> Cancel </Button>
      <Button variant="danger" :disabled="busy" @click="emit('confirm')">
        {{ busy ? 'Deleting...' : 'Delete' }}
      </Button>
    </div>
  </Modal>
</template>
