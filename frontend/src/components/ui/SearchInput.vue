<script setup lang="ts">
import { Search, X } from '@lucide/vue'
import { ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    debounce?: number
  }>(),
  { debounce: 300 },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const localValue = ref(props.modelValue)
let timeout: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.modelValue,
  (v) => {
    localValue.value = v
  },
)

function onInput(e: Event) {
  const value = (e.target as HTMLInputElement).value
  localValue.value = value
  if (timeout) clearTimeout(timeout)
  timeout = setTimeout(() => {
    emit('update:modelValue', value)
  }, props.debounce)
}

function clear() {
  localValue.value = ''
  emit('update:modelValue', '')
}
</script>

<template>
  <div class="relative">
    <Search
      class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
      aria-hidden="true"
    />
    <input
      type="text"
      :value="localValue"
      :placeholder="placeholder"
      class="min-h-10 w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm shadow-sm transition-all placeholder:text-slate-400 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
      aria-label="Search"
      @input="onInput"
    />
    <button
      v-if="localValue"
      type="button"
      class="absolute right-2 top-1/2 flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
      aria-label="Clear search"
      @click="clear"
    >
      <X :size="14" stroke-width="2" />
    </button>
  </div>
</template>
