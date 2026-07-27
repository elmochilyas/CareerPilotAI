<script setup lang="ts">
import type { JobOpportunity } from '../types'
import { formatDate } from '@/app/utils/date'

defineProps<{
  opportunity: JobOpportunity
}>()
</script>

<template>
  <div
    class="cursor-pointer rounded-lg border border-green-200 bg-white p-4 shadow-sm hover:shadow-md"
  >
    <div class="flex items-start justify-between">
      <div>
        <h3 class="font-medium text-gray-900">{{ opportunity.title }}</h3>
        <p v-if="opportunity.company_name" class="text-sm text-gray-500">
          {{ opportunity.company_name }}
        </p>
      </div>
      <span
        class="rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-600"
      >
        Confirmed
      </span>
    </div>
    <div class="mt-2 flex items-center gap-4 text-xs text-gray-400">
      <span>{{ formatDate(opportunity.saved_at) }}</span>
      <span v-if="opportunity.work_mode">{{ opportunity.work_mode }}</span>
    </div>
  </div>
</template>
