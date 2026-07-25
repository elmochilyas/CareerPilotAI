<script setup lang="ts">
import { computed } from 'vue'
import { Pencil, Archive, RefreshCw, Trash2, AlertTriangle } from '@lucide/vue'
import StateChip from './StateChip.vue'
import type { CandidateSkill } from '../types'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  skill: CandidateSkill
  saving: boolean
}>()
const emit = defineEmits<{
  edit: [value: CandidateSkill]
  archive: [value: CandidateSkill]
  restore: [value: CandidateSkill]
  delete: [value: CandidateSkill]
}>()

const displayName = computed(() => {
  if (props.skill.is_custom) return 'Custom Skill'
  return props.skill.skill?.name ?? 'Unknown Skill'
})

const showActions = computed(() => !props.saving)

const canArchive = computed(() => props.skill.state !== 'archived')
const canRestore = computed(() => props.skill.state === 'archived')
const canDelete = computed(
  () => props.skill.state === 'claimed' || props.skill.state === 'learning',
)

const yearsLabel = computed(() => {
  if (props.skill.years_experience == null) return null
  const y = Number(props.skill.years_experience)
  return `${y} ${y === 1 ? 'year' : 'years'}`
})
</script>

<template>
  <div
    class="group relative rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md"
  >
    <div class="flex items-start justify-between">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h3 class="truncate text-sm font-semibold text-slate-900">{{ displayName }}</h3>
          <span
            v-if="skill.is_custom"
            class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
            >custom</span
          >
        </div>
        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
          <StateChip :state="skill.state" />
          <span class="capitalize">{{ skill.proficiency_level }}</span>
          <span v-if="yearsLabel">&middot; {{ yearsLabel }}</span>
          <span v-if="skill.last_used_at">&middot; Last used {{ skill.last_used_at }}</span>
        </div>
        <div v-if="skill.evidence.length > 0" class="mt-2 text-xs text-slate-500">
          {{ skill.evidence.length }} evidence
          {{ skill.evidence.length === 1 ? 'entry' : 'entries' }}
        </div>
      </div>
    </div>

    <div
      v-if="skill.verification_at_risk"
      class="mt-2 flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs text-amber-700"
    >
      <AlertTriangle class="size-3.5" aria-hidden="true" />
      Verified but no evidence provided
    </div>

    <div v-if="showActions" class="mt-3 flex items-center gap-1.5 border-t border-slate-100 pt-3">
      <Button variant="ghost" size="sm" :disabled="saving" @click="emit('edit', skill)">
        <Pencil class="size-3.5" aria-hidden="true" />
        Manage evidence
      </Button>

      <Button
        v-if="canArchive"
        variant="ghost"
        size="sm"
        :disabled="saving"
        @click="emit('archive', skill)"
      >
        <Archive class="size-3.5" aria-hidden="true" />
        Archive
      </Button>

      <Button
        v-if="canRestore"
        variant="ghost"
        size="sm"
        class="text-primary-600 hover:bg-primary-50 hover:text-primary-700"
        :disabled="saving"
        @click="emit('restore', skill)"
      >
        <RefreshCw class="size-3.5" aria-hidden="true" />
        Restore
      </Button>

      <Button
        v-if="canDelete"
        variant="ghost"
        size="sm"
        class="ml-auto text-red-600 hover:bg-red-50 hover:text-red-700"
        :disabled="saving"
        @click="emit('delete', skill)"
      >
        <Trash2 class="size-3.5" aria-hidden="true" />
        Remove
      </Button>
    </div>
  </div>
</template>
