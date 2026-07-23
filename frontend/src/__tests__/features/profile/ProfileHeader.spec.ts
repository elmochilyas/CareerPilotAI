import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileHeader from '@/features/profile/components/ProfileHeader.vue'
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

describe('ProfileHeader', () => {
  it('renders name and headline', () => {
    const profile = makeProfile()
    const wrapper = mount(ProfileHeader, { props: { profile, saving: false } })
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('Senior Engineer')
  })

  it('shows completion percentage', () => {
    const profile = makeProfile()
    const wrapper = mount(ProfileHeader, { props: { profile, saving: false } })
    expect(wrapper.text()).toContain('65%')
  })

  it('shows location and availability', () => {
    const profile = makeProfile()
    const wrapper = mount(ProfileHeader, { props: { profile, saving: false } })
    expect(wrapper.text()).toContain('Paris')
    expect(wrapper.text()).toContain('France')
    expect(wrapper.text()).toContain('immediately')
  })

  it('enters editing mode on headline click', async () => {
    const profile = makeProfile()
    const wrapper = mount(ProfileHeader, { props: { profile, saving: false } })
    await wrapper.find('button').trigger('click')
    expect(wrapper.find('#profile-headline').exists()).toBe(true)
  })

  it('emits save with headline on form submit', async () => {
    const profile = makeProfile()
    const wrapper = mount(ProfileHeader, { props: { profile, saving: false } })
    await wrapper.find('button').trigger('click')
    const input = wrapper.find('#profile-headline')
    await input.setValue('Updated headline')
    await wrapper.find('form').trigger('submit.prevent')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      headline: 'Updated headline',
      updated_at: profile.updated_at,
    })
  })

  it('emits dirty on input change', async () => {
    const profile = makeProfile()
    const wrapper = mount(ProfileHeader, { props: { profile, saving: false } })
    await wrapper.find('button').trigger('click')
    await wrapper.find('#profile-headline').setValue('something')
    expect(wrapper.emitted('dirty')?.[0]).toEqual([true])
  })
})
