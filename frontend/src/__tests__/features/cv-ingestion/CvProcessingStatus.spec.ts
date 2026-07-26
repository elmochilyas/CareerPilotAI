import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvProcessingStatus from '@/features/cv-ingestion/components/CvProcessingStatus.vue'
import type { CvDocument } from '@/features/cv-ingestion/types'

function makeDoc(overrides: Partial<CvDocument> = {}): CvDocument {
  return {
    id: 1,
    original_name: 'resume.pdf',
    mime_type: 'application/pdf',
    size: 1024,
    status: 'pending',
    failure_reason: null,
    failure_code: null,
    metadata: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    latest_run: null,
    ...overrides,
  }
}

describe('CvProcessingStatus', () => {
  it('shows processing state for pending document', () => {
    const wrapper = mount(CvProcessingStatus, { props: { document: makeDoc() } })
    expect(wrapper.text()).toContain('Processing CV')
  })

  it('shows file name and size in summary', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: { document: makeDoc({ original_name: 'my-cv.pdf', size: 204800 }) },
    })
    expect(wrapper.text()).toContain('my-cv.pdf')
    expect(wrapper.text()).toContain('200')
  })

  it('shows Validating step for pending status', () => {
    const wrapper = mount(CvProcessingStatus, { props: { document: makeDoc() } })
    expect(wrapper.text()).toContain('Validating file')
  })

  it('shows Extracting text step for extracting status', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: { document: makeDoc({ status: 'extracting' }) },
    })
    expect(wrapper.text()).toContain('Extracting text')
  })

  it('shows Analyzing with AI step for analyzing status', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: { document: makeDoc({ status: 'analyzing' }) },
    })
    expect(wrapper.text()).toContain('Analyzing with AI')
  })

  it('shows preparing suggestions when document is ready_for_review', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: { document: makeDoc({ status: 'ready_for_review' }) },
    })
    expect(wrapper.text()).toContain('CV ready for review')
    expect(wrapper.text()).toContain('Preparing suggestions')
  })

  it('shows Review suggestions button when ready_for_review', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: { document: makeDoc({ status: 'ready_for_review' }) },
    })
    expect(wrapper.text()).toContain('Review suggestions')
  })

  it('shows failure heading when document failed', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: {
        document: makeDoc({
          status: 'failed',
          failure_reason: 'Something went wrong',
        }),
      },
    })
    expect(wrapper.text()).toContain('Processing failed')
    expect(wrapper.text()).toContain('Something went wrong')
  })

  it('maps failure_code to candidate-friendly message', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: {
        document: makeDoc({
          status: 'failed',
          failure_code: 'file_no_text',
          failure_reason: null,
        }),
      },
    })
    expect(wrapper.text()).not.toContain('file_no_text')
    expect(wrapper.text()).toContain('could not read any text')
  })

  it('shows Try again button when document failed', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: {
        document: makeDoc({ status: 'failed', failure_reason: 'Error' }),
      },
    })
    expect(wrapper.text()).toContain('Try again')
  })

  it('shows Upload new file button when document failed', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: {
        document: makeDoc({ status: 'failed', failure_reason: 'Error' }),
      },
    })
    expect(wrapper.text()).toContain('Upload new file')
  })

  it('emits retry event on try again button click', async () => {
    const wrapper = mount(CvProcessingStatus, {
      props: {
        document: makeDoc({ status: 'failed', failure_reason: 'Error' }),
      },
    })
    const btns = wrapper.findAll('button')
    const retryBtn = btns.find((b) => b.text().includes('Try again'))
    await retryBtn!.trigger('click')
    expect(wrapper.emitted('retry')).toBeTruthy()
  })

  it('emits uploadNew event on Upload new file button click', async () => {
    const wrapper = mount(CvProcessingStatus, {
      props: {
        document: makeDoc({ status: 'failed', failure_reason: 'Error' }),
      },
    })
    const btns = wrapper.findAll('button')
    const uploadBtn = btns.find((b) => b.text().includes('Upload new file'))
    await uploadBtn!.trigger('click')
    expect(wrapper.emitted('uploadNew')).toBeTruthy()
  })

  it('emits proceedToReview event on Review suggestions click', async () => {
    const wrapper = mount(CvProcessingStatus, {
      props: {
        document: makeDoc({ status: 'ready_for_review' }),
      },
    })
    const btns = wrapper.findAll('button')
    const reviewBtn = btns.find((b) => b.text().includes('Review suggestions'))
    await reviewBtn!.trigger('click')
    expect(wrapper.emitted('proceedToReview')).toBeTruthy()
  })

  it('shows processing tip while processing', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: { document: makeDoc({ status: 'analyzing' }) },
    })
    expect(wrapper.text()).toContain('usually takes a few seconds')
  })

  it('shows aria-live region', () => {
    const wrapper = mount(CvProcessingStatus, {
      props: { document: makeDoc() },
    })
    expect(wrapper.find('[aria-live="polite"]').exists()).toBe(true)
  })
})
