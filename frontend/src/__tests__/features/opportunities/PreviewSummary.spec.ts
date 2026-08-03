import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PreviewSummary from '@/features/opportunities/components/PreviewSummary.vue'
import type { PreviewData } from '@/features/opportunities/types'

function createPreview(overrides: Partial<PreviewData['data']> = {}): PreviewData {
  return {
    data: {
      overview: {},
      work_details: {},
      responsibilities: [],
      required_experience: [],
      preferred_experience: [],
      education: [],
      required_skills: [],
      preferred_skills: [],
      languages_certifications: [],
      compensation: null,
      dates: {},
      excluded: [],
      unknown_skills: [],
      warnings: [],
      ...overrides,
    },
    version_token: 'tok_v1',
    schema_version: '1.0.0',
  }
}

describe('PreviewSummary', () => {
  it('renders title', () => {
    const wrapper = mount(PreviewSummary, {
      props: { preview: createPreview() },
    })
    expect(wrapper.text()).toContain('Job Preview')
  })

  it('renders overview section', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          overview: { job_title: 'Developer', company: 'Acme' },
        }),
      },
    })
    expect(wrapper.text()).toContain('Overview')
    expect(wrapper.text()).toContain('Developer')
    expect(wrapper.text()).toContain('Acme')
  })

  it('renders work details section', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          work_details: { city: 'Paris', work_mode: 'Remote' },
        }),
      },
    })
    expect(wrapper.text()).toContain('Work Details')
    expect(wrapper.text()).toContain('Paris')
    expect(wrapper.text()).toContain('Remote')
  })

  it('renders responsibilities section', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          responsibilities: [{ id: 1, text: 'Lead team', source_evidence: null }],
        }),
      },
    })
    expect(wrapper.text()).toContain('Responsibilities')
    expect(wrapper.text()).toContain('Lead team')
  })

  it('renders required experience', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          required_experience: [{ id: 1, summary: 'PHP development', years: 5 }],
        }),
      },
    })
    expect(wrapper.text()).toContain('Required Experience')
    expect(wrapper.text()).toContain('PHP development')
    expect(wrapper.text()).toContain('5+ years')
  })

  it('renders preferred experience', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          preferred_experience: [{ id: 1, summary: 'Vue.js', years: 2 }],
        }),
      },
    })
    expect(wrapper.text()).toContain('Preferred Experience')
    expect(wrapper.text()).toContain('Vue.js')
  })

  it('renders education with field', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          education: [{ id: 1, degree: 'Bachelor', field: 'Computer Science', required: true }],
        }),
      },
    })
    expect(wrapper.text()).toContain('Education')
    expect(wrapper.text()).toContain('Bachelor')
    expect(wrapper.text()).toContain('Computer Science')
    expect(wrapper.text()).toContain('required')
  })

  it('renders required skills', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          required_skills: [
            {
              id: 1,
              label: 'PHP',
              proficiency: 'advanced',
              years_experience: null,
              resolution: 'exact',
            },
          ],
        }),
      },
    })
    expect(wrapper.text()).toContain('Required Skills')
    expect(wrapper.text()).toContain('PHP')
  })

  it('renders preferred skills', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          preferred_skills: [
            {
              id: 2,
              label: 'TypeScript',
              proficiency: null,
              years_experience: 3,
              resolution: 'alias',
            },
          ],
        }),
      },
    })
    expect(wrapper.text()).toContain('Preferred Skills')
    expect(wrapper.text()).toContain('TypeScript')
    expect(wrapper.text()).toContain('3y')
  })

  it('renders languages & certifications', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          languages_certifications: [
            { id: 1, type: 'language', name: 'English', required: true, proficiency: 'C1' },
          ],
        }),
      },
    })
    expect(wrapper.text()).toContain('Languages & Certifications')
    expect(wrapper.text()).toContain('English')
    expect(wrapper.text()).toContain('C1')
  })

  it('renders compensation section', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          compensation: { salary_min: 50000, salary_max: 80000, currency: 'EUR' },
        }),
      },
    })
    expect(wrapper.text()).toContain('Compensation')
    expect(wrapper.text()).toContain('50000')
    expect(wrapper.text()).toContain('EUR')
  })

  it('renders dates section', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          dates: { publication_date: '2026-07-01', application_deadline: '2026-08-01' },
        }),
      },
    })
    expect(wrapper.text()).toContain('Dates')
    expect(wrapper.text()).toContain('2026-07-01')
  })

  it('renders excluded section', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          excluded: [{ id: 1, type: 'benefit' }],
        }),
      },
    })
    expect(wrapper.text()).toContain('Excluded')
    expect(wrapper.text()).toContain('benefit')
  })

  it('renders unknown skills section', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          unknown_skills: [{ id: 1, type: 'required_skill' }],
        }),
      },
    })
    expect(wrapper.text()).toContain('Unknown Skills')
  })

  it('renders warnings', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          warnings: ['Some fields need attention'],
        }),
      },
    })
    expect(wrapper.text()).toContain('Some fields need attention')
  })

  it('hides empty sections', () => {
    const wrapper = mount(PreviewSummary, {
      props: { preview: createPreview() },
    })
    expect(wrapper.text()).not.toContain('Overview')
    expect(wrapper.text()).not.toContain('Responsibilities')
    expect(wrapper.text()).not.toContain('Compensation')
  })

  it('renders boolean values as Yes/No', () => {
    const wrapper = mount(PreviewSummary, {
      props: {
        preview: createPreview({
          overview: { travel_required: true },
        }),
      },
    })
    expect(wrapper.text()).toContain('Yes')
  })
})
