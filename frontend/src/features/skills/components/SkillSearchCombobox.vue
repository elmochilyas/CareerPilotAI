<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { Search, LoaderCircle } from '@lucide/vue'
import { searchSkills, skillKeys } from '../api'
import type { Skill } from '../types'

defineProps<{ modelValue: Skill | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: Skill | null]; select: [value: Skill] }>()

const query = ref('')
const debouncedQuery = ref('')
const isOpen = ref(false)
const activeIndex = ref(-1)
const inputRef = ref<HTMLInputElement | null>(null)
const listRef = ref<HTMLUListElement | null>(null)

let debounceTimer: ReturnType<typeof setTimeout>
watch(query, (v) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debouncedQuery.value = v
    activeIndex.value = -1
    if (v.length >= 2) isOpen.value = true
    else isOpen.value = false
  }, 300)
})

const searchQuery = useQuery({
  queryKey: skillKeys.catalogSearch(debouncedQuery.value),
  queryFn: () => searchSkills(debouncedQuery.value || undefined),
  enabled: debouncedQuery.value.length >= 2,
  staleTime: 60_000,
})

const results = computed(() => searchQuery.data.value ?? [])

function select(skill: Skill) {
  emit('update:modelValue', skill)
  emit('select', skill)
  query.value = skill.name
  isOpen.value = false
  activeIndex.value = -1
}

function onKeydown(e: KeyboardEvent) {
  if (!isOpen.value) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, results.value.length - 1)
    scrollToActive()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
    scrollToActive()
  } else if (e.key === 'Enter' && activeIndex.value >= 0) {
    e.preventDefault()
    select(results.value[activeIndex.value]!)
  } else if (e.key === 'Escape') {
    isOpen.value = false
    activeIndex.value = -1
  }
}

function scrollToActive() {
  listRef.value?.children[activeIndex.value]?.scrollIntoView({ block: 'nearest' })
}

function onBlur() {
  setTimeout(() => {
    isOpen.value = false
    activeIndex.value = -1
  }, 200)
}
</script>

<template>
  <div class="relative">
    <div class="relative">
      <Search
        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
        aria-hidden="true"
      />
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        role="combobox"
        :aria-expanded="isOpen"
        aria-haspopup="listbox"
        aria-autocomplete="list"
        :aria-activedescendant="activeIndex >= 0 ? `skill-option-${activeIndex}` : undefined"
        class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-sm shadow-sm transition-all focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
        placeholder="Search for a skill..."
        @keydown="onKeydown"
        @focus="debouncedQuery.length >= 2 ? (isOpen = true) : null"
        @blur="onBlur"
      />
      <LoaderCircle
        v-if="searchQuery.isPending.value && query.length >= 2"
        class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-slate-400"
        aria-hidden="true"
      />
    </div>

    <ul
      v-if="isOpen && results.length > 0"
      ref="listRef"
      role="listbox"
      class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg"
    >
      <li
        v-for="(skill, i) in results"
        :key="skill.id"
        :id="`skill-option-${i}`"
        role="option"
        :aria-selected="i === activeIndex"
        :class="[
          'cursor-pointer px-4 py-2.5 text-sm hover:bg-primary-50',
          i === activeIndex ? 'bg-primary-50' : '',
        ]"
        @mousedown.prevent="select(skill)"
      >
        <div class="font-medium text-slate-900">{{ skill.name }}</div>
        <div v-if="skill.category" class="text-xs text-slate-500">{{ skill.category }}</div>
      </li>
    </ul>

    <p
      v-if="
        isOpen && !searchQuery.isPending.value && results.length === 0 && debouncedQuery.length >= 2
      "
      class="mt-1 text-sm text-slate-500"
    >
      No skills found for "{{ debouncedQuery }}".
    </p>
  </div>
</template>
