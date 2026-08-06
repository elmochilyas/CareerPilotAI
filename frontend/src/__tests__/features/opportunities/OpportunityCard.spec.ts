import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import OpportunityCard from '@/features/opportunities/components/OpportunityCard.vue'
import type { JobOpportunity } from '@/features/opportunities/types'

function createOpportunity(overrides: Partial<JobOpportunity> = {}): JobOpportunity {
  return {
    id: 1,
    title: 'Senior Laravel Developer',
    company_name: 'Acme Corp',
    department: null,
    external_reference: null,
    summary: null,
    application_url: null,
    personal_label: null,
    source_url: null,
    city: null,
    region: null,
    country: null,
    work_mode: 'Remote',
    contract_type: null,
    seniority_level: null,
    working_hours: null,
    travel_required: null,
    relocation_required: null,
    salary_min: null,
    salary_max: null,
    salary_currency: null,
    salary_period: null,
    compensation_text: null,
    benefits: null,
    publication_date: null,
    application_deadline: null,
    expected_start_date: null,
    employment_duration: null,
    additional_requirements: null,
    requirements: [],
    skills: [],
    company: null,
    saved_at: '2026-07-26T12:00:00Z',
    created_at: '2026-07-26T12:00:00Z',
    updated_at: '2026-07-26T12:00:00Z',
    ...overrides,
  }
}

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/opportunities/:id', component: { template: '<div />' } }],
})

function mountOpportunityCard(opportunity = createOpportunity()) {
  return mount(OpportunityCard, {
    props: {
      opportunity,
      to: `/opportunities/${opportunity.id}`,
    },
    global: { plugins: [router] },
  })
}

describe('OpportunityCard', () => {
  it('renders title', () => {
    expect(mountOpportunityCard().text()).toContain('Senior Laravel Developer')
  })

  it('renders company name', () => {
    expect(mountOpportunityCard().text()).toContain('Acme Corp')
  })

  it('renders work mode', () => {
    expect(mountOpportunityCard().text()).toContain('Remote')
  })

  it('renders Saved badge', () => {
    expect(mountOpportunityCard().text()).toContain('Saved')
  })

  it('renders formatted date', () => {
    expect(mountOpportunityCard().text()).toContain('Jul 26, 2026')
  })

  it('does not render company row when null', () => {
    const wrapper = mountOpportunityCard(createOpportunity({ company_name: null }))
    expect(wrapper.text()).not.toContain('Acme Corp')
  })

  it('does not render work mode when null', () => {
    const wrapper = mountOpportunityCard(createOpportunity({ work_mode: null }))
    expect(wrapper.text()).not.toContain('null')
  })

  it('renders the opportunity action as a semantic navigation link', () => {
    expect(mountOpportunityCard().get('a').attributes('href')).toBe('/opportunities/1')
  })

  it('renders without crashing when the payload omits the skills list', () => {
    const opportunity = createOpportunity({ skills: undefined as unknown as never[] })
    const wrapper = mountOpportunityCard(opportunity)
    expect(wrapper.text()).toContain('Senior Laravel Developer')
    expect(wrapper.text()).not.toContain('undefined')
  })
})
