<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ResumeItem, TailoringRelevance } from '../types'

const props = defineProps<{
  items: ResumeItem[]
  sectionTitle: string
}>()
const emit = defineEmits<{ 'update:selected': [sourceRefs: string[]] }>()

const selectedRefs = ref<Set<string>>(
  new Set(props.items.filter((i) => i.metadata?.selected !== false).map((i) => i.source_ref)),
)

const searchQuery = ref('')

const filteredItems = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return props.items
  return props.items.filter(
    (item) =>
      item.original_text.toLowerCase().includes(q) || item.current_text.toLowerCase().includes(q),
  )
})

const selectedCount = computed(() => selectedRefs.value.size)

function toggleItem(sourceRef: string): void {
  const next = new Set(selectedRefs.value)
  if (next.has(sourceRef)) {
    next.delete(sourceRef)
  } else {
    next.add(sourceRef)
  }
  selectedRefs.value = next
  emit('update:selected', [...next])
}

function selectAll(): void {
  selectedRefs.value = new Set(props.items.map((i) => i.source_ref))
  emit('update:selected', [...selectedRefs.value])
}

function deselectAll(): void {
  selectedRefs.value = new Set()
  emit('update:selected', [])
}

function isSelected(sourceRef: string): boolean {
  return selectedRefs.value.has(sourceRef)
}

function relevanceColor(relevance: TailoringRelevance): string {
  const colors: Record<TailoringRelevance, string> = {
    high: 'bg-[var(--color-success-50)] text-[var(--color-success-700)] border-[var(--color-success-200)]',
    medium:
      'bg-[var(--color-primary-50)] text-[var(--color-primary-700)] border-[var(--color-primary-200)]',
    low: 'bg-[var(--color-warning-50)] text-[var(--color-warning-700)] border-[var(--color-warning-200)]',
    excluded:
      'bg-[var(--color-neutral-50)] text-[var(--text-muted)] border-[var(--color-neutral-200)]',
  }
  return colors[relevance]
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ sectionTitle }}</h3>
        <p class="mt-0.5 text-xs text-[var(--text-muted)]">
          {{ selectedCount }} of {{ items.length }} items selected
        </p>
      </div>
      <div class="flex gap-2">
        <button
          class="rounded-lg px-2.5 py-1 text-xs font-medium text-[var(--color-primary-600)] hover:bg-[var(--color-primary-50)]"
          @click="selectAll"
        >
          Select all
        </button>
        <button
          class="rounded-lg px-2.5 py-1 text-xs font-medium text-[var(--text-muted)] hover:bg-[var(--surface-secondary)]"
          @click="deselectAll"
        >
          Deselect all
        </button>
      </div>
    </div>

    <div class="relative">
      <svg
        class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[var(--text-muted)]"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <circle cx="11" cy="11" r="8" />
        <path d="m21 21-4.35-4.35" />
      </svg>
      <input
        v-model="searchQuery"
        type="text"
        name="cv-item-filter"
        aria-label="Filter CV items"
        autocomplete="off"
        placeholder="Filter items…"
        class="w-full rounded-lg border border-[var(--color-neutral-200)] bg-white py-2 pl-9 pr-3 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] focus:border-[var(--color-primary-400)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/20"
      />
    </div>

    <ul class="space-y-2" role="listbox" :aria-label="sectionTitle + ' items'">
      <li
        v-for="item in filteredItems"
        :key="item.source_ref"
        role="option"
        :aria-selected="isSelected(item.source_ref)"
        :class="[
          'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-all duration-150',
          isSelected(item.source_ref)
            ? 'border-[var(--color-primary-200)] bg-[var(--color-primary-50)]/50 shadow-[var(--shadow-neo-raised-sm)]'
            : 'border-[var(--color-neutral-100)] bg-white hover:border-[var(--color-neutral-200)] hover:shadow-[var(--shadow-neo-raised-sm)]',
        ]"
        @click="toggleItem(item.source_ref)"
      >
        <div class="mt-0.5 shrink-0">
          <div
            :class="[
              'flex size-5 items-center justify-center rounded-md border-2 transition-all duration-150',
              isSelected(item.source_ref)
                ? 'border-[var(--color-primary-600)] bg-[var(--color-primary-600)]'
                : 'border-[var(--color-neutral-300)] bg-white',
            ]"
          >
            <svg
              v-if="isSelected(item.source_ref)"
              class="size-3 text-white"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </div>
        </div>

        <div class="min-w-0 flex-1">
          <p class="text-sm text-[var(--text-primary)]">{{ item.current_text }}</p>
          <p
            v-if="item.original_text !== item.current_text"
            class="mt-1 text-xs text-[var(--text-muted)]"
          >
            Original: <span class="line-through">{{ item.original_text }}</span>
          </p>
        </div>

        <span
          v-if="item.metadata?.relevance"
          :class="[
            'shrink-0 rounded-md border px-1.5 py-0.5 text-[10px] font-semibold',
            relevanceColor(item.metadata.relevance as TailoringRelevance),
          ]"
        >
          {{ item.metadata.relevance }}
        </span>
      </li>
    </ul>

    <p
      v-if="filteredItems.length === 0 && searchQuery"
      class="py-4 text-center text-sm text-[var(--text-muted)]"
    >
      No items match "{{ searchQuery }}".
    </p>
  </div>
</template>
