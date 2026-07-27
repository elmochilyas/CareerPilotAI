<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { fetchOpportunity, opportunityKeys } from '../api'

const route = useRoute()
const router = useRouter()

const opportunityId = computed(() => {
  const id = Number(route.params.id)
  return Number.isNaN(id) || id <= 0 ? null : id
})

const {
  data: opportunity,
  isPending,
  isError,
} = useQuery({
  queryKey: computed(() => opportunityKeys.detail(opportunityId.value ?? 0)),
  queryFn: () => fetchOpportunity(opportunityId.value!),
  enabled: computed(() => opportunityId.value !== null),
  refetchOnMount: 'always',
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Opportunity details</h1>
      <button
        class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="router.push('/opportunities')"
      >
        Back to list
      </button>
    </div>

    <div
      v-if="opportunityId === null"
      class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
    >
      Invalid opportunity identifier.
    </div>

    <div v-else-if="isPending" class="py-12 text-center text-gray-500">Loading...</div>

    <div
      v-else-if="isError || !opportunity"
      class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
    >
      Failed to load opportunity.
    </div>

    <div
      v-else-if="!opportunity.title && !opportunity.company_name"
      class="rounded-lg border border-gray-200 bg-white p-6 text-center text-gray-500"
    >
      This opportunity has no data to display.
    </div>

    <div v-else class="space-y-6">
      <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="text-xl font-semibold">{{ opportunity.title }}</h2>
        <p v-if="opportunity.company_name" class="text-gray-500">
          {{ opportunity.company_name }}
        </p>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h3 class="mb-3 font-medium text-gray-900">Overview</h3>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div v-if="opportunity.department">
            <dt class="text-gray-400">Department</dt>
            <dd>{{ opportunity.department }}</dd>
          </div>
          <div v-if="opportunity.summary" class="col-span-2">
            <dt class="text-gray-400">Summary</dt>
            <dd class="text-gray-700">{{ opportunity.summary }}</dd>
          </div>
          <div v-if="opportunity.application_url">
            <dt class="text-gray-400">Application URL</dt>
            <dd>
              <a
                :href="opportunity.application_url"
                target="_blank"
                class="text-blue-600 hover:underline"
              >
                Apply
              </a>
            </dd>
          </div>
        </dl>
      </div>

      <div
        v-if="opportunity.city || opportunity.work_mode || opportunity.contract_type"
        class="rounded-lg border border-gray-200 bg-white p-6"
      >
        <h3 class="mb-3 font-medium text-gray-900">Work details</h3>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div v-if="opportunity.city">
            <dt class="text-gray-400">Location</dt>
            <dd>
              {{
                [opportunity.city, opportunity.region, opportunity.country]
                  .filter(Boolean)
                  .join(', ')
              }}
            </dd>
          </div>
          <div v-if="opportunity.work_mode">
            <dt class="text-gray-400">Work mode</dt>
            <dd>{{ opportunity.work_mode }}</dd>
          </div>
          <div v-if="opportunity.contract_type">
            <dt class="text-gray-400">Contract type</dt>
            <dd>{{ opportunity.contract_type }}</dd>
          </div>
          <div v-if="opportunity.seniority_level">
            <dt class="text-gray-400">Seniority</dt>
            <dd>{{ opportunity.seniority_level }}</dd>
          </div>
          <div v-if="opportunity.working_hours">
            <dt class="text-gray-400">Hours</dt>
            <dd>{{ opportunity.working_hours }}</dd>
          </div>
        </dl>
      </div>

      <div
        v-if="opportunity.requirements?.length"
        class="rounded-lg border border-gray-200 bg-white p-6"
      >
        <h3 class="mb-3 font-medium text-gray-900">Requirements</h3>
        <div
          v-for="req in opportunity.requirements"
          :key="req.id"
          class="border-b border-gray-100 py-2 last:border-0"
        >
          <p class="text-xs text-gray-400">{{ req.category }}</p>
          <p class="text-sm">{{ req.content }}</p>
        </div>
      </div>

      <div v-if="opportunity.skills?.length" class="rounded-lg border border-gray-200 bg-white p-6">
        <h3 class="mb-3 font-medium text-gray-900">Skills</h3>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="skill in opportunity.skills"
            :key="skill.id"
            class="rounded-full border px-3 py-1 text-xs"
            :class="
              skill.classification === 'required'
                ? 'border-blue-200 bg-blue-50 text-blue-700'
                : 'border-gray-200 bg-gray-50 text-gray-600'
            "
          >
            {{ skill.original_label }}
          </span>
        </div>
      </div>

      <div
        v-if="opportunity.salary_min || opportunity.compensation_text"
        class="rounded-lg border border-gray-200 bg-white p-6"
      >
        <h3 class="mb-3 font-medium text-gray-900">Compensation</h3>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div v-if="opportunity.salary_min">
            <dt class="text-gray-400">Salary</dt>
            <dd>
              {{ opportunity.salary_currency ?? '' }}
              {{ opportunity.salary_min }} - {{ opportunity.salary_max }}
              {{ opportunity.salary_period ? '/' + opportunity.salary_period : '' }}
            </dd>
          </div>
          <div v-if="opportunity.compensation_text">
            <dt class="text-gray-400">Compensation note</dt>
            <dd>{{ opportunity.compensation_text }}</dd>
          </div>
          <div v-if="opportunity.benefits?.length">
            <dt class="text-gray-400">Benefits</dt>
            <dd>
              <ul class="list-inside list-disc">
                <li v-for="b in opportunity.benefits" :key="b">{{ b }}</li>
              </ul>
            </dd>
          </div>
        </dl>
      </div>

      <div
        v-if="opportunity.publication_date || opportunity.application_deadline"
        class="rounded-lg border border-gray-200 bg-white p-6"
      >
        <h3 class="mb-3 font-medium text-gray-900">Dates</h3>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div v-if="opportunity.publication_date">
            <dt class="text-gray-400">Published</dt>
            <dd>{{ new Date(opportunity.publication_date).toLocaleDateString() }}</dd>
          </div>
          <div v-if="opportunity.application_deadline">
            <dt class="text-gray-400">Deadline</dt>
            <dd>{{ new Date(opportunity.application_deadline).toLocaleDateString() }}</dd>
          </div>
          <div v-if="opportunity.expected_start_date">
            <dt class="text-gray-400">Start date</dt>
            <dd>{{ new Date(opportunity.expected_start_date).toLocaleDateString() }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>
