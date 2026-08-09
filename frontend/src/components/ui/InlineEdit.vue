<script setup lang="ts">
import { Check, Pencil, X } from '@lucide/vue'
import { nextTick, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
  }>(),
  { placeholder: 'Click to edit' },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  save: [value: string]
}>()

const isEditing = ref(false)
const localValue = ref(props.modelValue)
const inputRef = ref<HTMLInputElement | null>(null)

watch(
  () => props.modelValue,
  (v) => {
    localValue.value = v
  },
)

async function startEdit() {
  localValue.value = props.modelValue
  isEditing.value = true
  await nextTick()
  inputRef.value?.focus()
  inputRef.value?.select()
}

function cancelEdit() {
  localValue.value = props.modelValue
  isEditing.value = false
}

function saveEdit() {
  const value = localValue.value.trim()
  emit('update:modelValue', value)
  emit('save', value)
  isEditing.value = false
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') {
    saveEdit()
  } else if (e.key === 'Escape') {
    cancelEdit()
  }
}
</script>

<template>
  <div v-if="!isEditing" class="group flex items-center gap-2">
    <span
      class="cursor-pointer rounded-lg px-1.5 py-0.5 text-sm transition-all duration-200 hover:bg-[var(--surface-secondary)] hover:shadow-[var(--shadow-neo-raised-sm)]"
      :class="modelValue ? 'text-[var(--text-primary)]' : 'text-[var(--text-muted)] italic'"
      role="button"
      tabindex="0"
      @click="startEdit"
      @keydown.enter="startEdit"
    >
      {{ modelValue || placeholder }}
    </span>
    <button
      type="button"
      class="invisible flex size-6 items-center justify-center rounded-lg text-[var(--text-muted)] transition-all duration-200 hover:bg-[var(--surface-secondary)] hover:shadow-[var(--shadow-neo-raised-sm)] hover:text-[var(--text-secondary)] focus:visible focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/40 group-hover:visible"
      aria-label="Edit"
      @click="startEdit"
    >
      <Pencil :size="12" stroke-width="2" />
    </button>
  </div>
  <div v-else class="flex items-center gap-1.5">
    <input
      ref="inputRef"
      v-model="localValue"
      type="text"
      :placeholder="placeholder"
      class="min-h-8 w-full max-w-xs rounded-xl bg-[var(--surface-secondary)] px-2.5 text-sm shadow-[var(--shadow-neo-inset)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
      @keydown="onKeydown"
    />
    <button
      type="button"
      class="flex size-7 items-center justify-center rounded-lg text-[var(--color-success-600)] transition-all duration-200 hover:bg-[var(--color-success-50)] hover:shadow-[var(--shadow-neo-raised-sm)] focus:outline-none focus:ring-2 focus:ring-[var(--color-success-500)]/40"
      aria-label="Save"
      @click="saveEdit"
    >
      <Check :size="14" stroke-width="2.5" />
    </button>
    <button
      type="button"
      class="flex size-7 items-center justify-center rounded-lg text-[var(--text-muted)] transition-all duration-200 hover:bg-[var(--surface-secondary)] hover:shadow-[var(--shadow-neo-raised-sm)] hover:text-[var(--text-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/40"
      aria-label="Cancel"
      @click="cancelEdit"
    >
      <X :size="14" stroke-width="2" />
    </button>
  </div>
</template>
