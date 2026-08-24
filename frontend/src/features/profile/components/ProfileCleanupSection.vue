<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import client from '@/api/client/axios'
import type { ApiData } from '../types'
import { profileKeys } from '../api'
import Button from '@/components/ui/Button.vue'
import { AlertTriangle, Eye, Layers } from '@lucide/vue'

interface DuplicateGroup {
  entity_type: string
  subtype: string
  classification: 'exact_duplicate' | 'possible_duplicate' | 'legitimate_separate'
  normalized_identity: string
  record_ids: number[]
  records: Array<Record<string, unknown>>
  reason: string
  similarity: number | null
  recommended_action: string
}

interface DuplicatesReport {
  profile_id: number
  user_id: number
  groups: DuplicateGroup[]
  summary: { exact: number; possible: number; legitimate: number }
}

async function fetchDuplicates(): Promise<DuplicatesReport> {
  return (await client.get<ApiData<DuplicatesReport>>('/api/v1/profile/duplicates')).data.data
}

const showDetails = ref(false)
const queryClient = useQueryClient()

const { data, isPending, isError, refetch } = useQuery({
  queryKey: [...profileKeys.all, 'duplicates'],
  queryFn: fetchDuplicates,
})

const exactGroups = computed(() => (data.value?.groups ?? []).filter((g) => g.classification === 'exact_duplicate'))
const possibleGroups = computed(() => (data.value?.groups ?? []).filter((g) => g.classification === 'possible_duplicate'))
const hasDuplicates = computed(() => (exactGroups.value.length + possibleGroups.value.length) > 0)

const cleanupItemsMutation = useMutation({
  mutationFn: async (payload: { keep_id: number; duplicate_ids: number[]; allow_possible?: boolean }) => {
    return (await client.post('/api/v1/profile/cleanup/items', payload)).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: profileKeys.all })
    refetch()
  },
  onError: (err: unknown) => {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Cleanup failed'
    alert(msg)
  },
})

const cleanupSkillsMutation = useMutation({
  mutationFn: async (payload: { keep_id: number; duplicate_ids: number[] }) => {
    return (await client.post('/api/v1/profile/cleanup/skills', payload)).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: profileKeys.all })
    refetch()
  },
})

const cleanupLanguagesMutation = useMutation({
  mutationFn: async () => {
    return (await client.post('/api/v1/profile/cleanup/languages', {})).data
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: profileKeys.all })
    refetch()
  },
})

function handleMergeItems(group: DuplicateGroup, allowPossible = false) {
  const confirmMsg = allowPossible
    ? `This is a possible (fuzzy) duplicate. Explicitly merge ${group.record_ids.length} records? Keep ${group.record_ids[0]} and delete the rest.`
    : `Merge ${group.record_ids.length} duplicate ${group.subtype} records? Keep ${group.record_ids[0]} and delete the rest.`
  if (!confirm(confirmMsg)) return
  const keepId = group.record_ids[0]!
  const dupIds = group.record_ids.slice(1)
  cleanupItemsMutation.mutate({ keep_id: keepId, duplicate_ids: dupIds, allow_possible: allowPossible })
}

function handleMergeSkills(group: DuplicateGroup) {
  if (!confirm(`Merge ${group.record_ids.length} duplicate skills?`)) return
  const keepId = group.record_ids[0]!
  const dupIds = group.record_ids.slice(1)
  cleanupSkillsMutation.mutate({ keep_id: keepId, duplicate_ids: dupIds })
}

function handleDedupLanguages() {
  if (!confirm('Deduplicate languages (keep best proficiency)?')) return
  cleanupLanguagesMutation.mutate()
}
</script>

<template>
  <div v-if="!isPending && hasDuplicates" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
    <div class="flex items-start gap-3">
      <AlertTriangle class="h-5 w-5 text-amber-600 mt-0.5" />
      <div class="flex-1">
        <p class="text-sm font-semibold text-amber-800">Profile cleanup recommended</p>
        <p class="text-xs text-amber-700 mt-1">
          Found {{ exactGroups.length }} exact duplicate{{ exactGroups.length !== 1 ? 's' : '' }}
          <span v-if="possibleGroups.length"> and {{ possibleGroups.length }} possible duplicate{{ possibleGroups.length !== 1 ? 's' : '' }} requiring review</span>.
        </p>
        <div class="mt-3 flex gap-2">
          <Button size="sm" variant="outline" @click="showDetails = !showDetails">
            <Eye class="mr-1 h-3.5 w-3.5" /> {{ showDetails ? 'Hide' : 'Review' }} duplicates
          </Button>
          <Button size="sm" variant="ghost" @click="refetch">Refresh</Button>
        </div>

        <div v-if="showDetails" class="mt-4 space-y-4">
          <!-- Exact groups -->
          <div v-for="(g, idx) in exactGroups" :key="'exact-' + idx" class="rounded-lg border border-amber-200 bg-white p-3">
            <p class="text-xs font-semibold text-slate-700">
              Exact duplicate — {{ g.subtype }} ({{ g.record_ids.length }} records)
            </p>
            <p class="text-xs text-slate-500 mt-1">Reason: {{ g.reason }} — keep one, merge metadata</p>
            <div class="mt-2 space-y-1">
              <div v-for="rec in g.records" :key="(rec as Record<string,unknown>).id as number" class="text-xs font-mono bg-slate-50 rounded px-2 py-1">
                {{ JSON.stringify(rec) }}
              </div>
            </div>
            <div class="mt-3 flex gap-2">
              <Button
                v-if="g.entity_type === 'profile_items'"
                size="sm"
                :loading="cleanupItemsMutation.isPending.value"
                @click="handleMergeItems(g)"
              >
                <Layers class="mr-1 h-3 w-3" /> Keep {{ g.record_ids[0] }} & merge
              </Button>
              <Button
                v-else-if="g.entity_type === 'candidate_skills'"
                size="sm"
                :loading="cleanupSkillsMutation.isPending.value"
                @click="handleMergeSkills(g)"
              >
                <Layers class="mr-1 h-3 w-3" /> Merge keep canonical
              </Button>
              <Button
                v-else-if="g.entity_type === 'languages'"
                size="sm"
                :loading="cleanupLanguagesMutation.isPending.value"
                @click="handleDedupLanguages"
              >
                <Layers class="mr-1 h-3 w-3" /> Deduplicate languages
              </Button>
            </div>
          </div>

          <!-- Possible groups -->
          <div v-for="(g, idx) in possibleGroups" :key="'possible-' + idx" class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <p class="text-xs font-semibold text-blue-700">Possible duplicate — {{ g.subtype }} ({{ g.record_ids.length }} records)</p>
            <p class="text-xs text-blue-600 mt-1">Reason: {{ g.reason }} <span v-if="g.similarity">({{ Math.round((g.similarity as number)*100) }}% similar)</span> — review manually</p>
            <div class="mt-2 space-y-1">
              <div v-for="rec in g.records" :key="(rec as Record<string,unknown>).id as number" class="text-xs font-mono bg-white rounded px-2 py-1 border">
                {{ JSON.stringify(rec) }}
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="text-xs text-slate-500">Keep separate or merge only after explicit confirmation.</span>
              <Button
                v-if="g.entity_type === 'profile_items'"
                size="sm"
                variant="outline"
                @click="handleMergeItems(g, true)"
              >
                Merge anyway (explicit)
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-else-if="isPending" class="text-xs text-slate-400">Checking for duplicates…</div>
  <div v-else-if="isError" class="text-xs text-red-500">Failed to check duplicates <Button size="sm" variant="ghost" @click="() => refetch()">Retry</Button></div>
</template>
