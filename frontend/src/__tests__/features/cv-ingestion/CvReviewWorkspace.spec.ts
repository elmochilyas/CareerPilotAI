import { describe, it, expect } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
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

  it('accepts every pending suggestion in one batch without changing reviewed decisions', async () => {
    const suggestions = [
      makeSuggestion('basic_information', { id: 11 }),
      makeSuggestion('experience', { id: 12, review_status: 'rejected' }),
      makeSuggestion('skill', { id: 13 }),
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

    const acceptAllButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Accept all pending (2)'))

    expect(acceptAllButton).toBeDefined()
    await acceptAllButton!.trigger('click')

    expect(wrapper.emitted('batchSave')).toEqual([
      [
        [
          { id: 11, decision: 'accepted' },
          { id: 13, decision: 'accepted' },
        ],
      ],
    ])
  })

  it('hides the accept-all action when there are no pending suggestions or review is readonly', () => {
    const reviewedSuggestion = makeSuggestion('basic_information', {
      review_status: 'accepted',
    })
    const reviewedWrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions: [reviewedSuggestion],
        preview: null,
        allReviewed: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    const readonlyWrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions: [makeSuggestion('basic_information')],
        preview: null,
        allReviewed: false,
        readonly: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })

    expect(reviewedWrapper.text()).not.toContain('Accept all pending')
    expect(readonlyWrapper.text()).not.toContain('Accept all pending')
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

  it('enables the apply action before the preview has loaded (no deadlock)', async () => {
    const suggestions = [makeSuggestion('basic_information', { review_status: 'accepted' })]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    await flushPromises()

    const reviewButtons = wrapper
      .findAll('button')
      .filter((b) => b.text().includes('Review import'))
    expect(reviewButtons.length).toBeGreaterThan(0)
    for (const button of reviewButtons) {
      expect(button.attributes('disabled')).toBeUndefined()
    }
    expect(wrapper.text()).not.toContain('Import preview is not ready yet')
  })

  it('does not show the green no-conflicts box until the preview has loaded', async () => {
    const suggestions = [makeSuggestion('basic_information', { review_status: 'accepted' })]
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview: null,
        allReviewed: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    await flushPromises()

    expect(wrapper.text()).not.toContain('No conflicts detected')
  })

  it('shows the green no-conflicts box once the preview has loaded', async () => {
    const suggestions = [makeSuggestion('basic_information', { review_status: 'accepted' })]
    const preview = {
      summary: { total: 1, accepted: 1, rejected: 0, keep_existing: 0, edited: 0 },
      conflicts: [],
    }
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview,
        allReviewed: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('No conflicts detected')
  })

  it('summarizes a 56-suggestion import as 50 changes ready to apply', async () => {
    const suggestions: CvSuggestion[] = []

    for (let i = 0; i < 5; i++) {
      suggestions.push(
        makeSuggestion('basic_information', {
          id: i + 1,
          field_name: 'phone',
          review_status: 'accepted',
        }),
      )
    }
    suggestions.push(makeSuggestion('headline', { id: 100, review_status: 'accepted' }))
    suggestions.push(makeSuggestion('summary', { id: 101, review_status: 'accepted' }))
    suggestions.push(makeSuggestion('experience', { id: 102, review_status: 'accepted' }))
    suggestions.push(makeSuggestion('education', { id: 103, review_status: 'accepted' }))
    suggestions.push(makeSuggestion('education', { id: 104, review_status: 'accepted' }))
    suggestions.push(makeSuggestion('project', { id: 105, review_status: 'accepted' }))
    suggestions.push(makeSuggestion('project', { id: 106, review_status: 'accepted' }))
    suggestions.push(makeSuggestion('project', { id: 107, review_status: 'accepted' }))
    for (let i = 0; i < 37; i++) {
      suggestions.push(makeSuggestion('skill', { id: 200 + i, review_status: 'accepted' }))
    }
    for (let i = 0; i < 4; i++) {
      suggestions.push(makeSuggestion('language', { id: 300 + i, review_status: 'accepted' }))
    }
    for (let i = 0; i < 2; i++) {
      suggestions.push(makeSuggestion('social_link', { id: 400 + i, review_status: 'accepted' }))
    }

    expect(suggestions).toHaveLength(56)

    const preview = {
      summary: { total: 56, accepted: 56, rejected: 0, keep_existing: 0, edited: 0 },
      conflicts: [],
    }
    const wrapper = mount(CvReviewWorkspace, {
      props: {
        suggestions,
        preview,
        allReviewed: true,
        applyPending: false,
        updateSuggestionPending: false,
        batchSavePending: false,
        batchSaveError: false,
        documentReviewable: true,
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('50 changes ready to apply')
  })
})
