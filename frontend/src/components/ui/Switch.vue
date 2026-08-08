<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  label?: string
  disabled?: boolean
  name?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

function toggle() {
  if (props.disabled) return
  emit('update:modelValue', !props.modelValue)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === ' ' || e.key === 'Enter') {
    e.preventDefault()
    toggle()
  }
}
</script>

<template>
  <div class="flex items-center gap-2.5">
    <button
      :id="name"
      type="button"
      role="switch"
      :aria-checked="modelValue"
      :aria-label="label"
      :disabled="disabled"
      class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
      :class="modelValue ? 'bg-primary-600' : 'bg-slate-300'"
      @click="toggle"
      @keydown="onKeydown"
    >
      <span
        class="pointer-events-none inline-block size-3.5 rounded-full bg-white shadow-sm ring-0 transition-transform"
        :class="modelValue ? 'translate-x-[18px]' : 'translate-x-[3px]'"
        aria-hidden="true"
      />
    </button>
    <label
      v-if="label"
      :for="name"
      class="cursor-pointer select-none text-sm text-slate-700"
      :class="disabled ? 'cursor-not-allowed opacity-50' : ''"
      @click="toggle"
    >
      {{ label }}
    </label>
  </div>
</template>
