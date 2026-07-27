import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
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

describe('OpportunityCard', () => {
  it('renders title', () => {
    const wrapper = mount(OpportunityCard, {
      props: { opportunity: createOpportunity() },
    })
    expect(wrapper.text()).toContain('Senior Laravel Developer')
  })

  it('renders company name', () => {
    const wrapper = mount(OpportunityCard, {
      props: { opportunity: createOpportunity() },
    })
    expect(wrapper.text()).toContain('Acme Corp')
  })

  it('renders work mode', () => {
    const wrapper = mount(OpportunityCard, {
      props: { opportunity: createOpportunity() },
    })
    expect(wrapper.text()).toContain('Remote')
  })

  it('renders Confirmed badge', () => {
    const wrapper = mount(OpportunityCard, {
      props: { opportunity: createOpportunity() },
    })
    expect(wrapper.text()).toContain('Confirmed')
  })

  it('renders formatted date', () => {
    const wrapper = mount(OpportunityCard, {
      props: { opportunity: createOpportunity() },
    })
    expect(wrapper.text()).toContain('Jul 26, 2026')
  })

  it('does not render company row when null', () => {
    const wrapper = mount(OpportunityCard, {
      props: { opportunity: createOpportunity({ company_name: null }) },
    })
    expect(wrapper.text()).not.toContain('Acme Corp')
  })

  it('does not render work mode when null', () => {
    const wrapper = mount(OpportunityCard, {
      props: { opportunity: createOpportunity({ work_mode: null }) },
    })
    expect(wrapper.text()).not.toContain('null')
  })
})
