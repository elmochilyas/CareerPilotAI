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
      class="cursor-pointer rounded px-1 text-sm transition-colors hover:bg-slate-100"
      :class="modelValue ? 'text-slate-900' : 'text-slate-400 italic'"
      role="button"
      tabindex="0"
      @click="startEdit"
      @keydown.enter="startEdit"
    >
      {{ modelValue || placeholder }}
    </span>
    <button
      type="button"
      class="invisible flex size-6 items-center justify-center rounded text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:visible focus:outline-none focus:ring-2 focus:ring-primary-500/40 group-hover:visible"
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
      class="min-h-8 w-full max-w-xs rounded border border-slate-300 px-2 text-sm shadow-sm focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
      @keydown="onKeydown"
    />
    <button
      type="button"
      class="flex size-7 items-center justify-center rounded text-emerald-600 transition-colors hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
      aria-label="Save"
      @click="saveEdit"
    >
      <Check :size="14" stroke-width="2.5" />
    </button>
    <button
      type="button"
      class="flex size-7 items-center justify-center rounded text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
      aria-label="Cancel"
      @click="cancelEdit"
    >
      <X :size="14" stroke-width="2" />
    </button>
  </div>
</template>
