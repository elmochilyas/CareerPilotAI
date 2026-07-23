<script setup lang="ts">
import { FileText, Pencil, LoaderCircle } from '@lucide/vue'
import { ref, watch } from 'vue'
import type { CandidateProfile, ProfileUpdate } from '../types'

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
  <section
    class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-all duration-300 hover:shadow-md"
    aria-labelledby="summary-heading"
  >
    <header
      class="flex items-center justify-between gap-3 border-b border-slate-100/80 bg-gradient-to-b from-slate-50/50 to-white px-5 py-4 sm:px-6"
    >
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-sm"
        >
          <FileText :size="16" stroke-width="1.5" />
        </div>
        <h2 id="summary-heading" class="text-base font-bold text-slate-900">
          Professional summary
        </h2>
      </div>
      <button
        v-if="profile.professional_summary && !editing"
        type="button"
        class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-500 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 active:scale-[0.97]"
        @click="editing = true"
      >
        <Pencil :size="13" stroke-width="1.5" />
        Edit
      </button>
    </header>

    <div class="px-5 pb-5 sm:px-6 sm:pb-6">
      <form v-if="editing" @submit.prevent="save">
        <label for="professional-summary" class="sr-only">Professional summary</label>
        <textarea
          id="professional-summary"
          v-model="summary"
          maxlength="5000"
          rows="6"
          class="w-full rounded-xl border border-slate-300 bg-white p-4 text-sm leading-relaxed shadow-sm transition-all focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30"
          @input="emit('dirty', true)"
        />
        <div class="mt-4 flex items-center justify-between">
          <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500"
            >{{ summary.length }}/5000</span
          >
          <div class="flex gap-2">
            <button
              type="button"
              class="min-h-10 cursor-pointer rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm transition-all hover:bg-slate-50 active:scale-[0.97]"
              @click="cancel"
            >
              Cancel
            </button>
            <button
              :disabled="saving"
              class="min-h-10 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-5 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97] disabled:opacity-60"
            >
              <span v-if="saving" class="inline-flex items-center gap-1.5">
                <LoaderCircle :size="14" class="animate-spin" />
                Saving…
              </span>
              <span v-else>Save summary</span>
            </button>
          </div>
        </div>
      </form>

      <div
        v-else-if="!profile.professional_summary"
        class="flex flex-col items-center py-12 text-center"
      >
        <div
          class="flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-primary-50 to-primary-100 shadow-inner"
        >
          <FileText :size="26" class="text-primary-500" stroke-width="1.3" />
        </div>
        <h3 class="mt-4 text-sm font-bold text-slate-900">Tell recruiters about yourself</h3>
        <p class="mt-1.5 max-w-xs text-xs leading-relaxed text-slate-500">
          Write a brief summary of your professional background, key skills, and career goals.
        </p>
        <button
          type="button"
          class="mt-5 min-h-10 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-5 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97]"
          @click="editing = true"
        >
          Add professional summary
        </button>
      </div>

      <div v-else class="relative">
        <p class="whitespace-pre-line text-sm leading-7 text-slate-700">
          {{ profile.professional_summary }}
        </p>
      </div>
    </div>
  </section>
</template>
