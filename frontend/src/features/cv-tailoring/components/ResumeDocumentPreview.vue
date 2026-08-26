<script setup lang="ts">
import { computed, shallowRef } from 'vue'

const props = defineProps<{
  resumeId: number
}>()

const isLoading = shallowRef(true)
const hasFailed = shallowRef(false)
const documentUrl = computed(() => `/api/v1/resumes/${props.resumeId}/document-preview`)

function handleLoad(): void {
  isLoading.value = false
  hasFailed.value = false
}

function handleError(): void {
  isLoading.value = false
  hasFailed.value = true
}
</script>

<template>
  <div class="document-preview">
    <p v-if="isLoading" class="document-status" role="status" aria-live="polite">
      Loading the final CV document…
    </p>
    <p v-if="hasFailed" class="document-error" role="alert">
      The final CV preview could not be loaded. Your saved CV is safe; please refresh and try again.
    </p>
    <iframe
      :src="documentUrl"
      title="Final tailored CV preview"
      class="document-frame"
      :class="{ 'document-frame--loading': isLoading }"
      @load="handleLoad"
      @error="handleError"
    />
  </div>
</template>

<style scoped>
.document-preview {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--color-neutral-200);
  border-radius: 0.75rem;
  background: var(--surface-secondary);
}

.document-status,
.document-error {
  padding: 1rem;
  font-size: 0.875rem;
}

.document-error {
  color: var(--color-error-700);
}

.document-frame {
  display: block;
  width: 100%;
  min-height: 1120px;
  border: 0;
  background: white;
}

.document-frame--loading {
  opacity: 0;
}

@media (max-width: 768px) {
  .document-frame {
    min-height: 900px;
  }
}
</style>
