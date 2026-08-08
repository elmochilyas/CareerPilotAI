<script setup lang="ts">
import { X } from '@lucide/vue'
import { ref } from 'vue'

const props = defineProps<{
  modelValue: string[]
  placeholder?: string
  max?: number
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

const input = ref('')
const inputEl = ref<HTMLInputElement | null>(null)

function add(raw: string) {
  const value = raw.trim()
  if (!value || (props.max !== undefined && props.modelValue.length >= props.max)) return
  if (props.modelValue.includes(value)) return
  emit('update:modelValue', [...props.modelValue, value])
  input.value = ''
}

function remove(index: number) {
  const next = [...props.modelValue]
  next.splice(index, 1)
  emit('update:modelValue', next)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault()
    add(input.value)
  }
  if (e.key === 'Backspace' && input.value === '' && props.modelValue.length > 0) {
    remove(props.modelValue.length - 1)
  }
}

function onPaste(e: ClipboardEvent) {
  const text = e.clipboardData?.getData('text') ?? ''
  if (/,|\n/.test(text)) {
    e.preventDefault()
    text.split(/[,|\n]/).forEach((part) => add(part))
  }
}
</script>

<template>
  <div
    class="flex min-h-[44px] flex-wrap items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 shadow-sm has-focus:border-primary-400 has-focus:ring-2 has-focus:ring-primary-500/30"
    @click="inputEl?.focus()"
  >
    <span
      v-for="(tag, i) in modelValue"
      :key="i"
      class="inline-flex select-none items-center gap-1 rounded-lg bg-primary-50 px-2.5 py-1 text-sm font-medium text-primary-800"
    >
      {{ tag }}
      <button
        v-if="!disabled"
        type="button"
        class="-mr-0.5 inline-flex size-4 cursor-pointer items-center justify-center rounded-full text-primary-500 transition-all hover:bg-primary-200 hover:text-primary-900"
        :aria-label="`Remove ${tag}`"
        @click.stop="remove(i)"
      >
        <X :size="12" stroke-width="2" />
      </button>
    </span>
    <input
      ref="inputEl"
      v-model="input"
      :disabled="disabled"
      :placeholder="
        !disabled && (max === undefined || modelValue.length < max)
          ? placeholder || 'Type and press Enter'
          : ''
      "
      class="min-w-[120px] flex-1 border-none bg-transparent px-1 py-1 text-sm outline-none placeholder:text-slate-400 disabled:cursor-not-allowed"
      @keydown="onKeydown"
      @paste="onPaste"
    />
  </div>
</template>
