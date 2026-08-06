import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvReviewFinal from '@/features/cv-ingestion/components/CvReviewFinal.vue'
import type { FinalStepSummary } from '@/features/cv-ingestion/components/CvReviewFinal.vue'
import type { CvSuggestion, ImportPreview } from '@/features/cv-ingestion/types'

const summary: FinalStepSummary = {
  fieldsToUpdate: [{ label: 'headline', stepIndex: 1 }],
  newItems: [{ count: 1, label: 'experience', stepIndex: 2 }],
  skillsToAdd: ['PHP'],
  itemsIgnored: [],
  conflicts: 0,
}

const steps = [
  { key: 'personal', label: 'Personal' },
  { key: 'profile', label: 'Profile' },
  { key: 'experience', label: 'Experience' },
  { key: 'final', label: 'Final Review' },
]

const stepStatuses = ['completed', 'completed', 'completed', 'completed']

const suggestions: CvSuggestion[] = []

function makePreview(overrides: Partial<ImportPreview> = {}): ImportPreview {
  return {
    summary: { total: 56, accepted: 56, rejected: 0, keep_existing: 0, edited: 0 },
    conflicts: [],
    ...overrides,
  }
}

describe('CvReviewFinal', () => {
  it('labels the review CTA "Review import"', () => {
    const wrapper = mount(CvReviewFinal, {
      props: {
        suggestions,
        preview: makePreview(),
        summary,
        steps,
        stepStatuses,
        applyDisabledReason: null,
        applyPending: false,
      },
    })
    expect(wrapper.text()).toContain('Review import')
  })

  it('hides the green no-conflicts box while the preview has not loaded', () => {
    const wrapper = mount(CvReviewFinal, {
      props: {
        suggestions,
        preview: null,
        summary,
        steps,
        stepStatuses,
        applyDisabledReason: null,
        applyPending: false,
      },
    })
    expect(wrapper.text()).not.toContain('No conflicts detected')
    expect(wrapper.text()).not.toContain('changes ready to apply')
  })

  it('shows the green box with the change count once the preview has loaded', () => {
    const wrapper = mount(CvReviewFinal, {
      props: {
        suggestions,
        preview: makePreview(),
        summary,
        steps,
        stepStatuses,
        applyDisabledReason: null,
        applyPending: false,
      },
    })
    expect(wrapper.text()).toContain('No conflicts detected')
    expect(wrapper.text()).toContain('3 changes ready to apply')
  })

  it('shows the conflicts panel and hides the green box when conflicts exist', () => {
    const withConflicts = makePreview({
      conflicts: [
        {
          type: 'experience',
          field: 'title',
          current: 'A',
          suggested: 'B',
          message: 'Title conflict',
        },
      ],
    })
    const wrapper = mount(CvReviewFinal, {
      props: {
        suggestions,
        preview: withConflicts,
        summary: { ...summary, conflicts: 1 },
        steps,
        stepStatuses,
        applyDisabledReason: '1 conflict(s) must be resolved.',
        applyPending: false,
      },
    })
    expect(wrapper.text()).toContain('1 conflict remaining')
    expect(wrapper.text()).not.toContain('No conflicts detected')
  })
})
