<script setup lang="ts">
import { computed } from 'vue'
import {
  Award,
  Briefcase,
  Code2,
  FileText,
  FolderKanban,
  GraduationCap,
  Languages,
} from '@lucide/vue'
import type { LucideIcon } from '@lucide/vue'
import type { MatchEvidenceRef, MatchFinding } from '../types'
import { evidenceEmptyMessage } from '../utils/matchPresentation'

const props = defineProps<{
  finding: MatchFinding
}>()

interface EvidenceTypeMeta {
  icon: LucideIcon
  label: string
}

const typeMeta = (type: string | null | undefined): EvidenceTypeMeta => {
  switch (type) {
    case 'candidate_skill':
    case 'skill':
      return { icon: Code2, label: 'Skill' }
    case 'experience':
      return { icon: Briefcase, label: 'Experience' }
    case 'project':
      return { icon: FolderKanban, label: 'Project' }
    case 'education':
      return { icon: GraduationCap, label: 'Education' }
    case 'certification':
      return { icon: Award, label: 'Certification' }
    case 'language':
      return { icon: Languages, label: 'Language' }
    default:
      return { icon: FileText, label: 'Evidence' }
  }
}

const refs = computed<MatchEvidenceRef[]>(() => props.finding.evidence_refs ?? [])
</script>

<template>
  <div class="min-w-0">
    <ul v-if="refs.length" class="flex flex-wrap gap-2" aria-label="Candidate evidence">
      <li
        v-for="(ref, index) in refs"
        :key="`${ref.type}:${ref.id ?? 'x'}:${index}`"
        class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 py-1 pr-2.5 pl-2 text-xs text-slate-700"
      >
        <component
          :is="typeMeta(ref.type).icon"
          class="size-3.5 shrink-0 text-slate-400"
          aria-hidden="true"
        />
        <span class="sr-only">{{ typeMeta(ref.type).label }}: </span>
        <span class="truncate">{{ ref.label }}</span>
      </li>
    </ul>
    <p v-else class="text-sm text-slate-500">{{ evidenceEmptyMessage(finding) }}</p>
  </div>
</template>
