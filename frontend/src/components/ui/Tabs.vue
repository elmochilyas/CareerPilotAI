<script setup lang="ts">
import type { FunctionalComponent, SVGAttributes } from 'vue'
import { ref } from 'vue'

const props = defineProps<{
  modelValue: string
  tabs: Array<{
    key: string
    label: string
    icon?: FunctionalComponent<SVGAttributes>
    disabled?: boolean
  }>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const tabRefs = ref<HTMLElement[]>([])

function selectTab(tab: string, isDisabled?: boolean) {
  if (isDisabled) return
  emit('update:modelValue', tab)
}

function onKeydown(e: KeyboardEvent, index: number) {
  const currentTab = props.tabs[index]
  if (!currentTab) return
  const enabledTabs = props.tabs.filter((t) => !t.disabled)
  const currentEnabledIndex = enabledTabs.findIndex((t) => t.key === currentTab.key)
  let nextIndex: number

  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault()
    nextIndex = (currentEnabledIndex + 1) % enabledTabs.length
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault()
    nextIndex = (currentEnabledIndex - 1 + enabledTabs.length) % enabledTabs.length
  } else if (e.key === 'Home') {
    e.preventDefault()
    nextIndex = 0
  } else if (e.key === 'End') {
    e.preventDefault()
    nextIndex = enabledTabs.length - 1
  } else {
    return
  }

  const nextTab = enabledTabs[nextIndex]
  if (nextTab) {
    emit('update:modelValue', nextTab.key)
    const targetIndex = props.tabs.findIndex((t) => t.key === nextTab.key)
    tabRefs.value[targetIndex]?.focus()
  }
}
</script>

<template>
  <div role="tablist" class="flex gap-1 border-b border-slate-200">
    <button
      v-for="(tab, index) in tabs"
      :key="tab.key"
      :ref="
        (el) => {
          if (el) tabRefs[index] = el as HTMLElement
        }
      "
      role="tab"
      :id="`tab-${tab.key}`"
      :aria-selected="modelValue === tab.key"
      :aria-controls="`panel-${tab.key}`"
      :tabindex="modelValue === tab.key ? 0 : -1"
      :disabled="tab.disabled"
      type="button"
      class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
      :class="
        modelValue === tab.key
          ? 'border-primary-600 text-primary-600'
          : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
      "
      @click="selectTab(tab.key, tab.disabled)"
      @keydown="onKeydown($event, index)"
    >
      <component :is="tab.icon" v-if="tab.icon" class="size-4" aria-hidden="true" />
      {{ tab.label }}
    </button>
  </div>
  <div
    v-for="tab in tabs"
    :key="tab.key"
    :id="`panel-${tab.key}`"
    role="tabpanel"
    :aria-labelledby="`tab-${tab.key}`"
    :hidden="modelValue !== tab.key"
    class="focus:outline-none"
  >
    <slot :active-tab="modelValue" />
  </div>
</template>
