import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfessionalLinks from '@/features/profile/components/ProfessionalLinks.vue'
import type { CandidateProfile } from '@/features/profile/types'

function makeProfile(overrides: Partial<CandidateProfile> = {}): CandidateProfile {
  return {
    id: 1,
    full_name: 'Jane Doe',
    headline: 'Senior Engineer',
    phone: null,
    city: 'Paris',
    country: 'France',
    linkedin_url: null,
    github_url: null,
    portfolio_url: null,
    availability_status: 'immediately',
    target_roles: [],
    preferred_locations: [],
    work_mode: null,
    contract_types: [],
    salary_min: null,
    salary_max: null,
    languages: [],
    professional_summary: null,
    profile_completion: 65,
    completion_details: { areas: [], missing_areas: [] },
    items: { education: [], experience: [], project: [], certification: [] },
    created_at: null,
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('ProfessionalLinks', () => {
  it('renders empty state when no links are set', () => {
    const wrapper = mount(ProfessionalLinks, { props: { profile: makeProfile() } })
    expect(wrapper.text()).toContain('Professional links')
  })

  it('renders only the set links without throwing when some links are null', () => {
    const wrapper = mount(ProfessionalLinks, {
      props: {
        profile: makeProfile({
          linkedin_url: 'https://linkedin.com/in/janedoe',
          github_url: 'https://github.com/janedoe',
        }),
      },
    })
    expect(wrapper.find('[aria-label="Open LinkedIn"]').exists()).toBe(true)
    expect(wrapper.find('[aria-label="Open GitHub"]').exists()).toBe(true)
    expect(wrapper.find('[aria-label="Open Portfolio"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('linkedin.com/in/janedoe')
    expect(wrapper.text()).toContain('github.com/janedoe')
  })

  it('strips scheme and trailing slash from displayed URLs', () => {
    const wrapper = mount(ProfessionalLinks, {
      props: {
        profile: makeProfile({ portfolio_url: 'https://portfolio.example.com/' }),
      },
    })
    expect(wrapper.text()).toContain('portfolio.example.com')
  })

  it('emits save with null for a cleared link', async () => {
    const wrapper = mount(ProfessionalLinks, {
      props: {
        profile: makeProfile({
          linkedin_url: 'https://linkedin.com/in/janedoe',
        }),
      },
    })
    await wrapper.find('button').trigger('click')
    await wrapper.find('input').setValue('')
    await wrapper.find('form').trigger('submit.prevent')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      linkedin_url: null,
      updated_at: '2026-01-01T00:00:00Z',
    })
  })
})
