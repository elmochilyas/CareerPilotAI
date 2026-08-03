<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { fetchOpportunity, opportunityKeys } from '../api'

const route = useRoute()

const opportunityId = computed(() => {
  const id = Number(route.params.id)
  return Number.isInteger(id) && id > 0 ? id : null
})

const opportunityQuery = useQuery({
  queryKey: computed(() => opportunityKeys.detail(opportunityId.value ?? 0)),
  queryFn: () => fetchOpportunity(opportunityId.value!),
  enabled: computed(() => opportunityId.value !== null),
  refetchOnMount: 'always',
})

const opportunity = opportunityQuery.data

const responsibilities = computed(
  () => opportunity.value?.requirements.filter((item) => item.category === 'responsibility') ?? [],
)

const experienceAndEducation = computed(
  () =>
    opportunity.value?.requirements.filter((item) =>
      ['required_experience', 'preferred_experience', 'education'].includes(item.category),
    ) ?? [],
)

const languagesAndCertifications = computed(
  () =>
    opportunity.value?.requirements.filter((item) =>
      ['language', 'certification'].includes(item.category),
    ) ?? [],
)

const requiredSkills = computed(
  () => opportunity.value?.skills.filter((skill) => skill.classification === 'required') ?? [],
)

const preferredSkills = computed(
  () => opportunity.value?.skills.filter((skill) => skill.classification === 'preferred') ?? [],
)

const hasWorkDetails = computed(() => {
  const item = opportunity.value
  return Boolean(
    item &&
    [
      item.city,
      item.region,
      item.country,
      item.work_mode,
      item.contract_type,
      item.seniority_level,
      item.working_hours,
      item.travel_required,
      item.relocation_required,
    ].some((value) => value !== null && value !== ''),
  )
})

const hasCompensation = computed(() => {
  const item = opportunity.value
  return Boolean(
    item &&
    (item.salary_min ||
      item.salary_max ||
      item.compensation_text ||
      (item.benefits && item.benefits.length > 0)),
  )
})

const hasDates = computed(() => {
  const item = opportunity.value
  return Boolean(
    item &&
    (item.publication_date ||
      item.application_deadline ||
      item.expected_start_date ||
      item.employment_duration),
  )
})

function formatLabel(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

function formatSalaryValue(value: string | null, currency: string | null): string {
  if (value === null) return '—'

  const amount = Number(value)

  if (!Number.isFinite(amount)) return value

  if (currency && /^[A-Z]{3}$/.test(currency)) {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      maximumFractionDigits: 2,
    }).format(amount)
  }

  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(amount)
}
</script>

<template>
  <main class="opportunity-detail">
    <header class="detail-header">
      <div>
        <p class="detail-eyebrow">Saved opportunity</p>
        <h1>Opportunity details</h1>
      </div>
      <RouterLink class="back-link" :to="{ name: 'opportunities' }">Back to list</RouterLink>
    </header>

    <div v-if="opportunityId === null" class="detail-message detail-message-error" role="alert">
      Invalid opportunity identifier.
    </div>

    <div
      v-else-if="opportunityQuery.isPending.value"
      class="detail-message"
      role="status"
      aria-live="polite"
    >
      Loading opportunity details…
    </div>

    <div
      v-else-if="opportunityQuery.isError.value || !opportunity"
      class="detail-message detail-message-error"
      role="alert"
    >
      <p>We couldn’t load this opportunity.</p>
      <button type="button" @click="opportunityQuery.refetch()">Try again</button>
    </div>

    <div v-else class="detail-content">
      <section class="detail-hero" aria-labelledby="opportunity-title">
        <div>
          <h2 id="opportunity-title">{{ opportunity.title }}</h2>
          <p v-if="opportunity.company_name">{{ opportunity.company_name }}</p>
        </div>
        <span v-if="opportunity.personal_label" class="personal-label">
          {{ opportunity.personal_label }}
        </span>
      </section>

      <section
        v-if="
          opportunity.department ||
          opportunity.summary ||
          opportunity.application_url ||
          opportunity.external_reference
        "
        class="detail-section"
        aria-labelledby="overview-heading"
      >
        <h2 id="overview-heading">Overview</h2>
        <dl class="detail-grid">
          <div v-if="opportunity.department">
            <dt>Department</dt>
            <dd>{{ opportunity.department }}</dd>
          </div>
          <div v-if="opportunity.external_reference">
            <dt>Reference</dt>
            <dd>{{ opportunity.external_reference }}</dd>
          </div>
          <div v-if="opportunity.summary" class="detail-span">
            <dt>Summary</dt>
            <dd class="prose-value">{{ opportunity.summary }}</dd>
          </div>
          <div v-if="opportunity.application_url">
            <dt>Application</dt>
            <dd>
              <a :href="opportunity.application_url" target="_blank" rel="noopener noreferrer">
                Open application page
              </a>
            </dd>
          </div>
        </dl>
      </section>

      <section v-if="hasWorkDetails" class="detail-section" aria-labelledby="work-heading">
        <h2 id="work-heading">Work details</h2>
        <dl class="detail-grid">
          <div v-if="opportunity.city || opportunity.region || opportunity.country">
            <dt>Location</dt>
            <dd>
              {{
                [opportunity.city, opportunity.region, opportunity.country]
                  .filter(Boolean)
                  .join(', ')
              }}
            </dd>
          </div>
          <div v-if="opportunity.work_mode">
            <dt>Work mode</dt>
            <dd>{{ formatLabel(opportunity.work_mode) }}</dd>
          </div>
          <div v-if="opportunity.contract_type">
            <dt>Contract type</dt>
            <dd>{{ formatLabel(opportunity.contract_type) }}</dd>
          </div>
          <div v-if="opportunity.seniority_level">
            <dt>Seniority</dt>
            <dd>{{ formatLabel(opportunity.seniority_level) }}</dd>
          </div>
          <div v-if="opportunity.working_hours">
            <dt>Working hours</dt>
            <dd>{{ opportunity.working_hours }}</dd>
          </div>
          <div v-if="opportunity.travel_required !== null">
            <dt>Travel required</dt>
            <dd>{{ opportunity.travel_required ? 'Yes' : 'No' }}</dd>
          </div>
          <div v-if="opportunity.relocation_required !== null">
            <dt>Relocation required</dt>
            <dd>{{ opportunity.relocation_required ? 'Yes' : 'No' }}</dd>
          </div>
        </dl>
      </section>

      <section
        v-if="responsibilities.length"
        class="detail-section"
        aria-labelledby="responsibilities-heading"
      >
        <h2 id="responsibilities-heading">Responsibilities</h2>
        <ol class="numbered-list">
          <li v-for="item in responsibilities" :key="item.id">
            <span>{{ item.content }}</span>
            <details v-if="item.source_evidence">
              <summary>Source evidence</summary>
              <p>{{ item.source_evidence }}</p>
            </details>
          </li>
        </ol>
      </section>

      <section
        v-if="experienceAndEducation.length"
        class="detail-section"
        aria-labelledby="experience-heading"
      >
        <h2 id="experience-heading">Experience and education</h2>
        <ul class="requirement-list">
          <li v-for="item in experienceAndEducation" :key="item.id">
            <span>{{ item.content }}</span>
            <small>{{ formatLabel(item.category) }}</small>
          </li>
        </ul>
      </section>

      <section
        v-if="requiredSkills.length || preferredSkills.length"
        class="detail-section"
        aria-labelledby="skills-heading"
      >
        <h2 id="skills-heading">Skills</h2>
        <div v-if="requiredSkills.length" class="skill-group">
          <h3>Required</h3>
          <ul class="skill-list">
            <li v-for="skill in requiredSkills" :key="skill.id">
              <span>{{ skill.original_label }}</span>
              <small>{{ skill.skill_id ? 'Catalog matched' : 'Original label' }}</small>
            </li>
          </ul>
        </div>
        <div v-if="preferredSkills.length" class="skill-group">
          <h3>Preferred</h3>
          <ul class="skill-list">
            <li v-for="skill in preferredSkills" :key="skill.id">
              <span>{{ skill.original_label }}</span>
              <small>{{ skill.skill_id ? 'Catalog matched' : 'Original label' }}</small>
            </li>
          </ul>
        </div>
      </section>

      <section
        v-if="languagesAndCertifications.length"
        class="detail-section"
        aria-labelledby="language-heading"
      >
        <h2 id="language-heading">Languages and certifications</h2>
        <ul class="requirement-list">
          <li v-for="item in languagesAndCertifications" :key="item.id">
            <span>{{ item.content }}</span>
            <small>
              {{ formatLabel(item.category) }}
              <template v-if="item.language_proficiency">
                · {{ formatLabel(item.language_proficiency) }}
              </template>
            </small>
          </li>
        </ul>
      </section>

      <section v-if="hasCompensation" class="detail-section" aria-labelledby="comp-heading">
        <h2 id="comp-heading">Compensation and benefits</h2>
        <dl class="detail-grid">
          <div v-if="opportunity.salary_min || opportunity.salary_max">
            <dt>Salary</dt>
            <dd>
              {{ formatSalaryValue(opportunity.salary_min, opportunity.salary_currency) }}–{{
                formatSalaryValue(opportunity.salary_max, opportunity.salary_currency)
              }}
              {{ opportunity.salary_period ? `/${formatLabel(opportunity.salary_period)}` : '' }}
            </dd>
          </div>
          <div v-if="opportunity.compensation_text" class="detail-span">
            <dt>Compensation note</dt>
            <dd>{{ opportunity.compensation_text }}</dd>
          </div>
          <div v-if="opportunity.benefits?.length" class="detail-span">
            <dt>Benefits</dt>
            <dd>
              <ul class="bullet-list">
                <li v-for="benefit in opportunity.benefits" :key="benefit">{{ benefit }}</li>
              </ul>
            </dd>
          </div>
        </dl>
      </section>

      <section v-if="hasDates" class="detail-section" aria-labelledby="dates-heading">
        <h2 id="dates-heading">Dates</h2>
        <dl class="detail-grid">
          <div v-if="opportunity.publication_date">
            <dt>Published</dt>
            <dd>{{ formatDate(opportunity.publication_date) }}</dd>
          </div>
          <div v-if="opportunity.application_deadline">
            <dt>Application deadline</dt>
            <dd>{{ formatDate(opportunity.application_deadline) }}</dd>
          </div>
          <div v-if="opportunity.expected_start_date">
            <dt>Expected start</dt>
            <dd>{{ formatDate(opportunity.expected_start_date) }}</dd>
          </div>
          <div v-if="opportunity.employment_duration">
            <dt>Employment duration</dt>
            <dd>{{ opportunity.employment_duration }}</dd>
          </div>
        </dl>
      </section>

      <section
        v-if="opportunity.additional_requirements?.length"
        class="detail-section"
        aria-labelledby="additional-heading"
      >
        <h2 id="additional-heading">Additional requirements</h2>
        <ul class="bullet-list">
          <li v-for="item in opportunity.additional_requirements" :key="item.id">
            {{ item.text }}
          </li>
        </ul>
      </section>

      <section class="detail-section source-section" aria-labelledby="source-heading">
        <h2 id="source-heading">Source</h2>
        <dl class="detail-grid">
          <div>
            <dt>Saved</dt>
            <dd>{{ formatDate(opportunity.saved_at) }}</dd>
          </div>
          <div v-if="opportunity.source_url">
            <dt>Original posting</dt>
            <dd>
              <a :href="opportunity.source_url" target="_blank" rel="noopener noreferrer">
                Open source
              </a>
            </dd>
          </div>
        </dl>
      </section>
    </div>
  </main>
</template>

<style scoped>
.opportunity-detail {
  display: grid;
  width: 100%;
  max-width: 64rem;
  gap: 1.5rem;
  margin: 0 auto;
  color: #172033;
}

.detail-header,
.detail-hero {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.detail-eyebrow {
  color: #667085;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.detail-header h1 {
  margin-top: 0.25rem;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.back-link,
.detail-message button {
  min-height: 2.75rem;
  border: 1px solid #d0d5dd;
  border-radius: 0.5rem;
  background: #fff;
  padding: 0.625rem 0.875rem;
  color: #344054;
  font-size: 0.875rem;
  font-weight: 650;
}

.detail-content {
  display: grid;
  gap: 1rem;
}

.detail-hero,
.detail-section,
.detail-message {
  border: 1px solid #e4e7ec;
  border-radius: 0.75rem;
  background: #fff;
  padding: 1.25rem;
}

.detail-hero h2 {
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.detail-hero p {
  margin-top: 0.25rem;
  color: #667085;
}

.personal-label {
  border-radius: 999px;
  background: #eef2ff;
  padding: 0.25rem 0.625rem;
  color: #4338ca;
  font-size: 0.75rem;
  font-weight: 650;
}

.detail-section > h2 {
  margin-bottom: 1rem;
  font-size: 1.0625rem;
  font-weight: 700;
}

.detail-grid {
  display: grid;
  gap: 1rem;
}

.detail-grid div {
  min-width: 0;
}

.detail-grid dt {
  color: #667085;
  font-size: 0.75rem;
  font-weight: 650;
}

.detail-grid dd {
  margin-top: 0.25rem;
  overflow-wrap: anywhere;
  color: #172033;
  font-size: 0.875rem;
}

.detail-grid a,
.source-section a {
  color: #4338ca;
  font-weight: 650;
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.prose-value {
  max-width: 72ch;
  white-space: pre-wrap;
  line-height: 1.6;
}

.numbered-list,
.requirement-list,
.skill-list,
.bullet-list {
  display: grid;
  gap: 0.625rem;
}

.numbered-list {
  list-style: decimal;
  padding-left: 1.5rem;
}

.bullet-list {
  list-style: disc;
  padding-left: 1.25rem;
}

.numbered-list li,
.requirement-list li,
.skill-list li {
  line-height: 1.5;
}

.numbered-list details {
  margin-top: 0.25rem;
  color: #667085;
  font-size: 0.75rem;
}

.numbered-list details p {
  margin-top: 0.25rem;
  white-space: pre-wrap;
}

.requirement-list li,
.skill-list li {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  border-bottom: 1px solid #f2f4f7;
  padding-bottom: 0.625rem;
}

.requirement-list small,
.skill-list small {
  color: #667085;
  font-size: 0.75rem;
}

.skill-group + .skill-group {
  margin-top: 1.25rem;
}

.skill-group h3 {
  margin-bottom: 0.625rem;
  color: #667085;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}

.detail-message {
  color: #667085;
  text-align: center;
}

.detail-message-error {
  border-color: #fecdca;
  background: #fef3f2;
  color: #b42318;
}

.detail-message button {
  margin-top: 0.75rem;
  cursor: pointer;
}

:is(.back-link, .detail-message button, .detail-grid a):focus-visible {
  outline: 2px solid #4f46e5;
  outline-offset: 2px;
}

@media (min-width: 48rem) {
  .detail-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .detail-span {
    grid-column: 1 / -1;
  }

  .detail-hero,
  .detail-section,
  .detail-message {
    padding: 1.5rem;
  }
}
</style>
