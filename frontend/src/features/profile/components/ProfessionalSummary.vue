<script setup lang="ts">
import { FileText, LoaderCircle } from '@lucide/vue'
import { ref, watch } from 'vue'
import type { CandidateProfile, ProfileUpdate } from '../types'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{ profile: CandidateProfile; saving: boolean }>()
const emit = defineEmits<{ save: [value: ProfileUpdate]; dirty: [value: boolean] }>()

const editing = ref(false)
const summary = ref(props.profile.professional_summary ?? '')

watch(
  () => props.profile.professional_summary,
  (v) => {
    if (!editing.value) summary.value = v ?? ''
  },
)

function cancel() {
  summary.value = props.profile.professional_summary ?? ''
  editing.value = false
  emit('dirty', false)
}

function save() {
  const newVal = summary.value || null
  const oldVal = props.profile.professional_summary ?? null
  if (newVal !== oldVal) {
    emit('save', {
      professional_summary: newVal,
      updated_at: props.profile.updated_at,
    })
  }
  editing.value = false
  emit('dirty', false)
}
</script>

<template>
  <Card>
    <template #header>
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-lg bg-[var(--color-primary-100)] text-[var(--color-primary-600)]"
        >
          <FileText :size="16" />
        </div>
        <h2 class="text-base font-semibold text-[var(--text-primary)]">Professional summary</h2>
      </div>
      <Button
        v-if="profile.professional_summary && !editing"
        variant="outline"
        size="sm"
        @click="editing = true"
      >
        Edit
      </Button>
    </template>

    <form v-if="editing" @submit.prevent="save">
      <label for="professional-summary" class="sr-only">Professional summary</label>
      <textarea
        id="professional-summary"
        v-model="summary"
        maxlength="5000"
        rows="6"
        class="w-full rounded-xl bg-[var(--surface-inset)] p-4 text-sm leading-relaxed shadow-[var(--shadow-neo-inset)] text-[var(--text-primary)] placeholder:text-[var(--text-muted)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
        @input="emit('dirty', true)"
      />
      <div class="mt-4 flex items-center justify-between">
        <span
          class="rounded-lg bg-[var(--surface-secondary)] px-2.5 py-1 text-xs font-medium text-[var(--text-muted)]"
        >
          {{ summary.length }}/5000
        </span>
        <div class="flex gap-2">
          <Button variant="outline" type="button" @click="cancel">Cancel</Button>
          <Button type="submit" :disabled="saving">
            <span v-if="saving" class="inline-flex items-center gap-1.5">
              <LoaderCircle :size="14" class="animate-spin" />
              Saving...
            </span>
            <span v-else>Save summary</span>
          </Button>
        </div>
      </div>
    </form>

    <div
      v-else-if="!profile.professional_summary"
      class="flex flex-col items-center py-10 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-[var(--surface-secondary)] shadow-[var(--shadow-neo-inset)]"
      >
        <FileText :size="22" class="text-[var(--text-muted)]" />
      </div>
      <h3 class="mt-3 text-sm font-semibold text-[var(--text-primary)]">
        Tell recruiters about yourself
      </h3>
      <p class="mt-1 max-w-xs text-xs text-[var(--text-muted)]">
        Write a brief summary of your professional background, key skills, and career goals.
      </p>
      <div class="mt-4">
        <Button @click="editing = true">Add professional summary</Button>
      </div>
    </div>

    <div v-else class="relative">
      <p class="whitespace-pre-line text-sm leading-relaxed text-[var(--text-secondary)]">
        {{ profile.professional_summary }}
      </p>
    </div>
  </Card>
</template>
