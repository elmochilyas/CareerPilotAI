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
      class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
      :class="
        modelValue
          ? 'bg-[var(--color-primary-600)] shadow-[var(--shadow-neo-button)]'
          : 'bg-[var(--surface-secondary)] shadow-[var(--shadow-neo-inset)]'
      "
      @click="toggle"
      @keydown="onKeydown"
    >
      <span
        class="pointer-events-none inline-block size-4.5 rounded-full bg-white shadow-[var(--shadow-neo-raised-sm)] ring-0 transition-transform duration-200"
        :class="modelValue ? 'translate-x-[22px]' : 'translate-x-[3px]'"
        aria-hidden="true"
      />
    </button>
    <label
      v-if="label"
      :for="name"
      class="cursor-pointer select-none text-sm text-[var(--text-secondary)]"
      :class="disabled ? 'cursor-not-allowed opacity-50' : ''"
      @click="toggle"
    >
      {{ label }}
    </label>
  </div>
</template>
