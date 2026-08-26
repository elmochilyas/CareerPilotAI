<script setup lang="ts">
import { computed } from 'vue'
import { ExternalLink } from '@lucide/vue'
import Badge from '@/components/ui/Badge.vue'
import { formatDate } from '@/app/utils/date'
import type { CompanyResearchBrief, ResearchClaim } from '../../types'

const props = defineProps<{ brief: CompanyResearchBrief | null }>()

function domain(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, '')
  } catch {
    return url
  }
}

function isSafeUrl(url: string): boolean {
  try {
    const u = new URL(url)
    return u.protocol === 'https:' || u.protocol === 'http:'
  } catch {
    return false
  }
}

function humanizeType(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

type SectionDef = { label: string; claims: ResearchClaim[] }

const sections = computed<SectionDef[]>(() => {
  const b = props.brief
  if (!b) return []
  return [
    { label: 'Products & services', claims: b.products ?? [] },
    { label: 'Technology', claims: b.technology_context ?? [] },
    { label: 'Role context', claims: b.role_context ?? [] },
    { label: 'Recent updates', claims: b.recent_information ?? [] },
    { label: 'Candidate preparation', claims: b.candidate_preparation ?? [] },
  ]
})

function supportsFor(sourceId: number): string {
  const labels: string[] = []
  for (const sec of sections.value) {
    if (sec.claims.some((c) => c.source_ids.includes(sourceId))) {
      labels.push(sec.label)
    }
  }
  return labels.join(', ')
}

function kindForSource(sourceId: number): 'fact' | 'inference' | 'unknown' {
  const allClaims = sections.value.flatMap((s) => s.claims)
  const related = allClaims.filter((c) => c.source_ids.includes(sourceId))
  if (related.length === 0) return 'unknown'
  const kinds = related.map((c) => c.kind)
  if (kinds.includes('inference')) return 'inference'
  if (kinds.every((k) => k === 'fact')) return 'fact'
  if (kinds.includes('fact')) return 'fact'
  return 'unknown'
}

function badgeVariant(kind: 'fact' | 'inference' | 'unknown'): 'success' | 'warning' | 'default' {
  if (kind === 'fact') return 'success'
  if (kind === 'inference') return 'warning'
  return 'default'
}

const badgeLabel = (kind: 'fact' | 'inference' | 'unknown'): string => kind.toUpperCase()

const rows = computed(() => {
  const b = props.brief
  if (!b || !b.sources?.length) return []
  return b.sources
})
</script>

<template>
  <div class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-primary)]">
    <div class="border-b border-[var(--border-default)] px-5 py-4 sm:px-6">
      <h3 class="text-sm font-semibold text-[var(--text-primary)]">Source & provenance</h3>
      <p class="mt-1 text-xs text-[var(--text-muted)]">Every claim is traceable to its source.</p>
    </div>

    <div v-if="rows.length === 0" class="px-6 py-10 text-center">
      <p class="text-sm text-[var(--text-muted)]">No sources available for this research.</p>
    </div>

    <!-- Desktop table -->
    <div v-else class="hidden overflow-x-auto sm:block">
      <table class="w-full text-left text-sm">
        <thead>
          <tr
            class="border-b border-[var(--border-default)] bg-[var(--surface-secondary)] text-xs uppercase tracking-wide text-[var(--text-muted)]"
          >
            <th class="px-5 py-3 font-semibold sm:px-6">Source</th>
            <th class="px-5 py-3 font-semibold">Type</th>
            <th class="px-5 py-3 font-semibold">Supports</th>
            <th class="px-5 py-3 font-semibold sm:px-6">Retrieved</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border-default)]">
          <tr
            v-for="s in rows"
            :key="s.id"
            class="transition-colors hover:bg-[var(--surface-secondary)]/60"
          >
            <td class="px-5 py-4 sm:px-6">
              <div class="max-w-[22ch] truncate font-medium text-[var(--text-primary)]">
                {{ s.title ?? domain(s.url) }}
              </div>
              <a
                v-if="isSafeUrl(s.url)"
                :href="s.url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
              >
                {{ domain(s.url) }}
                <ExternalLink class="size-3 shrink-0" aria-hidden="true" />
              </a>
              <span v-else class="mt-0.5 block break-all text-xs text-[var(--text-muted)]">{{
                s.url
              }}</span>
              <div class="mt-0.5 text-[11px] text-[var(--text-muted)]">
                {{ humanizeType(s.source_type) }}
              </div>
            </td>
            <td class="px-5 py-4 align-top">
              <Badge :variant="badgeVariant(kindForSource(s.id))" size="sm">{{
                badgeLabel(kindForSource(s.id))
              }}</Badge>
            </td>
            <td
              class="max-w-[24ch] px-5 py-4 align-top text-xs leading-relaxed text-[var(--text-secondary)]"
            >
              {{ supportsFor(s.id) || 'General' }}
            </td>
            <td
              class="whitespace-nowrap px-5 py-4 align-top text-xs text-[var(--text-muted)] sm:px-6"
            >
              {{ formatDate(s.retrieved_at) || '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile cards -->
    <div v-if="rows.length > 0" class="divide-y divide-[var(--border-default)] sm:hidden">
      <div v-for="s in rows" :key="s.id" class="p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="truncate text-sm font-semibold text-[var(--text-primary)]">
              {{ s.title ?? domain(s.url) }}
            </div>
            <a
              v-if="isSafeUrl(s.url)"
              :href="s.url"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary-600)]"
            >
              {{ domain(s.url) }}
              <ExternalLink class="size-3 shrink-0" aria-hidden="true" />
            </a>
            <span v-else class="mt-0.5 block break-all text-xs text-[var(--text-muted)]">{{
              s.url
            }}</span>
          </div>
          <Badge :variant="badgeVariant(kindForSource(s.id))" size="sm">{{
            badgeLabel(kindForSource(s.id))
          }}</Badge>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
          <div>
            <dt class="font-medium text-[var(--text-muted)]">Supports</dt>
            <dd class="mt-0.5 text-[var(--text-secondary)]">
              {{ supportsFor(s.id) || 'General' }}
            </dd>
          </div>
          <div>
            <dt class="font-medium text-[var(--text-muted)]">Retrieved</dt>
            <dd class="mt-0.5 text-[var(--text-muted)]">{{ formatDate(s.retrieved_at) || '—' }}</dd>
          </div>
          <div class="col-span-2">
            <dt class="font-medium text-[var(--text-muted)]">Source type</dt>
            <dd class="mt-0.5 text-[var(--text-secondary)]">{{ humanizeType(s.source_type) }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>
