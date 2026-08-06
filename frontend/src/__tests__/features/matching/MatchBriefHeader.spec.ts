import { describe, expect, it } from 'vitest'
import { h } from 'vue'
import { mount } from '@vue/test-utils'
import MatchBriefHeader from '@/features/matching/components/MatchBriefHeader.vue'
import type { JobOpportunity } from '@/features/opportunities/types'

function makeOpportunity(overrides: Partial<JobOpportunity> = {}): JobOpportunity {
  return {
    id: 5,
    title: 'Senior Laravel Developer',
    company_name: 'Acme',
    department: null,
    external_reference: null,
    summary: null,
    application_url: null,
    personal_label: null,
    source_url: null,
    city: 'Paris',
    region: null,
    country: 'France',
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
    saved_at: '2026-07-27T10:00:00.000Z',
    created_at: '2026-07-27T10:00:00.000Z',
    updated_at: '2026-07-27T10:00:00.000Z',
    ...overrides,
  }
}

function mountHeader(overrides: {
  opportunity?: JobOpportunity | null
  classifierUnavailable?: boolean
}) {
  const opportunity =
    overrides.opportunity !== undefined ? overrides.opportunity : makeOpportunity()
  return mount(MatchBriefHeader, {
    props: {
      opportunity,
      classifierUnavailable: overrides.classifierUnavailable ?? false,
    },
    global: {
      stubs: {
        RouterLink: {
          name: 'RouterLink',
          props: ['to'],
          setup(_: unknown, { slots }: { slots: Record<string, unknown> }) {
            return () => h('a', slots.default?.() as never)
          },
        },
      },
    },
  })
}

describe('MatchBriefHeader', () => {
  it('renders the opportunity context with a back link', () => {
    const wrapper = mountHeader({})

    expect(wrapper.text()).toContain('Back to opportunity')
    expect(wrapper.text()).toContain('Senior Laravel Developer')
    expect(wrapper.text()).toContain('Acme · Paris, France · Remote')
  })

  it('falls back to the brief title without an opportunity', () => {
    const wrapper = mountHeader({ opportunity: null })

    expect(wrapper.text()).toContain('Career Intelligence Brief')
    expect(wrapper.text()).not.toContain('Back to opportunity')
  })

  it('announces a deterministic-fallback analysis in human language', () => {
    const wrapper = mountHeader({ classifierUnavailable: true })

    expect(wrapper.find('[role="status"]').text()).toContain(
      "Some requirements couldn't be verified automatically. Your score is based on the information we could confirm.",
    )
    expect(wrapper.find('details').attributes('open')).toBeUndefined()
    expect(wrapper.find('summary').text()).not.toContain('semantic comparison')
  })

  it('keeps the technical explanation behind a Learn more disclosure', async () => {
    const wrapper = mountHeader({ classifierUnavailable: true })

    expect(wrapper.find('summary').text()).toContain('Learn more')

    await wrapper.find('summary').trigger('click')

    expect(wrapper.find('details').attributes('open')).toBeDefined()
    expect(wrapper.text()).toContain('semantic comparison was unavailable')
  })

  it('omits the fallback notice when the classifier ran', () => {
    const wrapper = mountHeader({ classifierUnavailable: false })

    expect(wrapper.text()).not.toContain("couldn't be verified automatically")
  })
})
