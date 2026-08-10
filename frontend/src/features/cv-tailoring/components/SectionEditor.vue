<script setup lang="ts">
import { ref } from 'vue'
import type { ResumeSection } from '../types'

defineProps<{
  section: ResumeSection
}>()

const emit = defineEmits<{
  'update:text': [sourceRef: string, text: string]
}>()

const editingRef = ref<string | null>(null)
const editText = ref('')

function startEditing(sourceRef: string, currentText: string): void {
  editingRef.value = sourceRef
  editText.value = currentText
}

function saveEdit(sourceRef: string): void {
  emit('update:text', sourceRef, editText.value)
  editingRef.value = null
  editText.value = ''
}

function cancelEdit(): void {
  editingRef.value = null
  editText.value = ''
}

function isEditing(sourceRef: string): boolean {
  return editingRef.value === sourceRef
}
</script>

<template>
  <div class="space-y-3">
    <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ section.title }}</h3>

    <ul class="space-y-2" role="list">
      <li
        v-for="item in section.items"
        :key="item.source_ref"
        class="rounded-xl border border-[var(--color-neutral-100)] bg-white p-3"
      >
        <template v-if="isEditing(item.source_ref)">
          <textarea
            v-model="editText"
            rows="3"
            class="w-full rounded-lg border border-[var(--color-primary-300)] bg-white p-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] focus:border-[var(--color-primary-400)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/20"
            :aria-label="'Edit text for ' + item.source_ref"
          />
          <div class="mt-2 flex justify-end gap-2">
            <button
              class="rounded-lg px-3 py-1.5 text-xs font-medium text-[var(--text-muted)] hover:bg-[var(--surface-secondary)]"
              @click="cancelEdit"
            >
              Cancel
            </button>
            <button
              class="rounded-lg bg-[var(--color-primary-600)] px-3 py-1.5 text-xs font-medium text-white hover:bg-[var(--color-primary-700)]"
              @click="saveEdit(item.source_ref)"
            >
              Save
            </button>
          </div>
        </template>

        <template v-else>
          <div class="flex items-start justify-between gap-3">
            <p class="text-sm text-[var(--text-primary)]">{{ item.current_text }}</p>
            <button
              class="shrink-0 rounded-lg p-1.5 text-[var(--text-muted)] transition-colors hover:bg-[var(--surface-secondary)] hover:text-[var(--color-primary-600)]"
              :aria-label="'Edit text for ' + item.source_ref"
              @click="startEditing(item.source_ref, item.current_text)"
            >
              <svg
                class="size-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
              </svg>
            </button>
          </div>
          <p
            v-if="item.original_text !== item.current_text"
            class="mt-1 text-xs text-[var(--text-muted)]"
          >
            Original: <span class="line-through">{{ item.original_text }}</span>
          </p>
        </template>
      </li>
    </ul>
  </div>
</template>
