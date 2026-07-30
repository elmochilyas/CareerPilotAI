import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import OpportunityDetailPage from '@/features/opportunities/pages/OpportunityDetailPage.vue'
import type { JobOpportunity } from '@/features/opportunities/types'

const { opportunityData } = vi.hoisted(() => ({
  opportunityData: { value: undefined as JobOpportunity | undefined },
}))

vi.mock('@tanstack/vue-query', () => ({
  useQuery: vi.fn<(...args: unknown[]) => unknown>(() => ({
    data: ref(opportunityData.value),
    isPending: ref(false),
    isError: ref(false),
    refetch: vi.fn<() => void>(),
  })),
}))

vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: {
    detail: (id: number) => ['opportunities', 'detail', id],
  },
  fetchOpportunity: vi.fn<(...args: unknown[]) => unknown>(),
}))

function createOpportunity(): JobOpportunity {
  return {
    id: 8,
    title: 'Platform Engineer',
    company_name: 'Acme',
    department: 'Infrastructure',
    external_reference: 'JOB-42',
    summary: 'Build and operate the internal platform.',
    application_url: 'https://example.com/apply',
    personal_label: 'Top choice',
    source_url: 'https://example.com/jobs/42',
    city: null,
    region: 'Île-de-France',
    country: 'France',
    work_mode: 'hybrid',
    contract_type: 'full_time',
    seniority_level: 'mid_level',
    working_hours: '40 hours',
    travel_required: false,
    relocation_required: false,
    salary_min: '70000.00',
    salary_max: '90000.00',
    salary_currency: 'EUR',
    salary_period: 'year',
    compensation_text: 'Annual bonus available.',
    benefits: ['Health insurance'],
    publication_date: '2026-07-01',
    application_deadline: '2026-08-01',
    expected_start_date: '2026-09-01',
    employment_duration: 'Permanent',
    additional_requirements: [
      { id: 5, text: 'Occasional on-call rotation.', source_evidence: null },
    ],
    requirements: [
      {
        id: 1,
        category: 'responsibility',
        content: 'Operate production services.',
        classification: null,
        language: null,
        language_proficiency: null,
        source_evidence: 'Operate our production platform.',
        display_order: 0,
      },
      {
        id: 2,
        category: 'required_experience',
        content: 'Three years of backend experience.',
        classification: 'required',
        language: null,
        language_proficiency: null,
        source_evidence: null,
        display_order: 1,
      },
      {
        id: 3,
        category: 'language',
        content: 'English',
        classification: 'required',
        language: 'English',
        language_proficiency: 'advanced',
        source_evidence: null,
        display_order: 2,
      },
    ],
    skills: [
      {
        id: 1,
        skill_id: 10,
        original_label: 'Laravel',
        classification: 'required',
        proficiency: null,
        years_experience: null,
        source_evidence: null,
        display_order: 0,
      },
      {
        id: 2,
        skill_id: null,
        original_label: 'Internal platform tooling',
        classification: 'preferred',
        proficiency: null,
        years_experience: null,
        source_evidence: null,
        display_order: 1,
      },
    ],
    company: null,
    saved_at: '2026-07-30T12:00:00Z',
    created_at: '2026-07-30T12:00:00Z',
    updated_at: '2026-07-30T12:00:00Z',
  }
}

async function mountPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/opportunities', name: 'opportunities', component: { template: '<div />' } },
      {
        path: '/opportunities/:id',
        name: 'opportunities-detail',
        component: OpportunityDetailPage,
      },
    ],
  })
  await router.push('/opportunities/8')
  await router.isReady()

  return mount(OpportunityDetailPage, {
    global: { plugins: [router] },
  })
}

describe('OpportunityDetailPage', () => {
  beforeEach(() => {
    opportunityData.value = createOpportunity()
  })

  it('renders all persisted opportunity sections in readonly mode', async () => {
    const wrapper = await mountPage()

    for (const text of [
      'Platform Engineer',
      'Infrastructure',
      'JOB-42',
      'Île-de-France, France',
      'Travel required',
      'No',
      'Operate production services.',
      'Three years of backend experience.',
      'Laravel',
      'Internal platform tooling',
      'English',
      'Health insurance',
      'Permanent',
      'Occasional on-call rotation.',
      'Open source',
    ]) {
      expect(wrapper.text()).toContain(text)
    }
  })

  it('identifies catalog-matched and original skill labels', async () => {
    const wrapper = await mountPage()

    expect(wrapper.text()).toContain('Catalog matched')
    expect(wrapper.text()).toContain('Original label')
  })

  it('does not expose edit, delete, or mutation actions', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('input').exists()).toBe(false)
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Edit')
    expect(wrapper.text()).not.toContain('Delete')
  })
})
