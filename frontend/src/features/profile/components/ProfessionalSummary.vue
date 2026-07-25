<script setup lang="ts">
import { FileText, LoaderCircle } from '@lucide/vue'
import { ref, watch } from 'vue'
import type { CandidateProfile, ProfileUpdate } from '../types'
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
  <section aria-labelledby="summary-heading">
    <header class="flex items-center justify-between gap-3 border-b border-slate-100 py-4">
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-lg bg-primary-100 text-primary-600"
        >
          <FileText :size="16" />
        </div>
        <h2 id="summary-heading" class="text-base font-semibold text-slate-900">
          Professional summary
        </h2>
      </div>
      <Button
        v-if="profile.professional_summary && !editing"
        variant="outline"
        size="sm"
        @click="editing = true"
      >
        Edit
      </Button>
    </header>

    <div>
      <form v-if="editing" @submit.prevent="save">
        <label for="professional-summary" class="sr-only">Professional summary</label>
        <textarea
          id="professional-summary"
          v-model="summary"
          maxlength="5000"
          rows="6"
          class="w-full rounded-lg border border-slate-300 bg-white p-4 text-sm leading-relaxed shadow-sm transition-all focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
          @input="emit('dirty', true)"
        />
        <div class="mt-4 flex items-center justify-between">
          <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">
            {{ summary.length }}/5000
          </span>
          <div class="flex gap-2">
            <Button variant="outline" type="button" @click="cancel"> Cancel </Button>
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
        <div class="flex size-12 items-center justify-center rounded-full bg-slate-100">
          <FileText :size="22" class="text-slate-400" />
        </div>
        <h3 class="mt-3 text-sm font-semibold text-slate-900">Tell recruiters about yourself</h3>
        <p class="mt-1 max-w-xs text-xs text-slate-500">
          Write a brief summary of your professional background, key skills, and career goals.
        </p>
        <div class="mt-4">
          <Button @click="editing = true">Add professional summary</Button>
        </div>
      </div>

      <div v-else class="relative">
        <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">
          {{ profile.professional_summary }}
        </p>
      </div>
    </div>
  </section>
</template>
