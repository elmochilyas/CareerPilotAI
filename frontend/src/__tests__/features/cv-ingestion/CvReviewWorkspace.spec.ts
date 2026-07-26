import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvReviewWorkspace from '@/features/cv-ingestion/components/CvReviewWorkspace.vue'
import type { CvSuggestion } from '@/features/cv-ingestion/types'
import CvReviewStepper from '@/features/cv-ingestion/components/CvReviewStepper.vue'
import CvReviewFooter from '@/features/cv-ingestion/components/CvReviewFooter.vue'

function makeSuggestion(type: string, overrides: Partial<CvSuggestion> = {}): CvSuggestion {
  const base: Record<string, unknown> = {
    id: Math.random(),
    cv_document_id: 1,
    type,
    category: null,
    field_name: type === 'basic_information' ? 'phone' : null,
    current_value: null,
    source_page: null,
    source_text: null,
    extraction_method: 'ai_extraction',
    confidence: 0.95,
    review_status: 'pending',
    reviewed_decision: null,
    reviewed_at: null,
  }

  if (type === 'basic_information') base.suggested_value = { value: '+212 600 000 000' }
  else if (type === 'headline') base.suggested_value = { value: 'Senior Developer' }
  else if (type === 'summary') base.suggested_value = { value: 'Experienced developer.' }
  else if (type === 'experience')
    base.suggested_value = {
      title: 'Developer',
      organization: 'Acme',
      location: null,
      start_date: null,
      end_date: null,
      is_current: false,
      description: null,
      technologies: [],
    }
  else if (type === 'education')
    base.suggested_value = {
      degree: 'BS',
      institution: 'MIT',
      field_of_study: null,
      location: null,
      start_date: null,
      end_date: null,
      is_current: false,
      description: null,
    }
  else if (type === 'project')
    base.suggested_value = {
      name: 'Project X',
      role: null,
      description: null,
      technologies: [],
      url: null,
      start_date: null,
      end_date: null,
      is_current: false,
    }
  else if (type === 'certification')
    base.suggested_value = {
      name: 'AWS Certified',
      issuer: null,
      date: null,
      url: null,
      description: null,
    }
  else if (type === 'social_link')
    base.suggested_value = { type: 'github', url: 'https://github.com/test' }
  else if (type === 'skill') base.suggested_value = { name: 'PHP', category: 'Backend' }
  else if (type === 'language') base.suggested_value = { language: 'Arabic', proficiency: 'native' }
  else base.suggested_value = { value: 'test' }

  return { ...base, ...overrides } as unknown as CvSuggestion
}

describe('CvReviewWorkspace', () => {
  it('renders stepper with dynamic steps based on suggestion types', () => {
    const suggestions = [
      makeSuggestion('basic_information'),
      makeSuggestion('experience'),
      makeSuggestion('skill'),
    ]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: false,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.text()).toContain('Personal')
    expect(wrapper.text()).toContain('Experience')
    expect(wrapper.text()).toContain('Skills')
    expect(wrapper.text()).toContain('Final Review')
  })

  it('does not show step for missing suggestion types', () => {
    const suggestions = [makeSuggestion('basic_information'), makeSuggestion('headline')]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: false,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.text()).toContain('Personal')
    expect(wrapper.text()).toContain('Profile')
    expect(wrapper.text()).not.toContain('Experience')
    expect(wrapper.text()).not.toContain('Skills')
  })

  it('shows first incomplete step on mount', () => {
    const suggestions = [
      makeSuggestion('basic_information', { id: 1, review_status: 'accepted' }),
      makeSuggestion('experience', { id: 2 }),
    ]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: false,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.text()).toContain('Experience')
  })

  it('renders the stepper component', () => {
    const suggestions = [makeSuggestion('basic_information')]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: false,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.findComponent(CvReviewStepper).exists()).toBe(true)
  })

  it('renders the footer', () => {
    const suggestions = [makeSuggestion('basic_information')]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: false,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.findComponent(CvReviewFooter).exists()).toBe(true)
  })

  it('shows readonly banner when readonly is true', () => {
    const suggestions = [makeSuggestion('basic_information')]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: true,
        readonly: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.text()).toContain('Viewing imported CV')
  })

  it('shows "Upload different CV" button only when not readonly', () => {
    const suggestions = [makeSuggestion('basic_information')]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: false,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.text()).toContain('Upload different CV')

    const readonlyWrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: true,
        readonly: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(readonlyWrapper.text()).not.toContain('Upload different CV')
  })

  it('shows no-suggestions message when suggestions array is empty', () => {
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions: [],
        preview: null,
        allReviewed: false,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    expect(wrapper.text()).toContain('No suggestions to review')
  })
})
