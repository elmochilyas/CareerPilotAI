import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvImportPreview from '@/features/cv-ingestion/components/CvImportPreview.vue'
import type { ImportPreview } from '@/features/cv-ingestion/types'

function makePreview(overrides: Partial<ImportPreview> = {}): ImportPreview {
  return {
    summary: { total: 5, accepted: 3, rejected: 1, keep_existing: 1, edited: 0 },
    conflicts: [],
    ...overrides,
  }
}

describe('CvImportPreview', () => {
  it('shows loading state when pending', () => {
    const wrapper = mount(CvImportPreview, { props: { preview: null, isPending: true } })
    expect(wrapper.text()).toContain('Preparing preview')
  })

  it('shows summary totals', () => {
    const wrapper = mount(CvImportPreview, { props: { preview: makePreview(), isPending: false } })
    expect(wrapper.text()).toContain('5')
    expect(wrapper.text()).toContain('3')
    expect(wrapper.text()).toContain('1')
  })

  it('shows no conflicts message when conflicts are empty', () => {
    const wrapper = mount(CvImportPreview, { props: { preview: makePreview(), isPending: false } })
    expect(wrapper.text()).toContain('No conflicts detected')
  })

  it('shows conflicts when present', () => {
    const preview = makePreview({
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
    const wrapper = mount(CvImportPreview, { props: { preview, isPending: false } })
    expect(wrapper.text()).toContain('Conflicts detected')
    expect(wrapper.text()).toContain('Title conflict')
  })

  it('emits confirm event', async () => {
    const wrapper = mount(CvImportPreview, { props: { preview: makePreview(), isPending: false } })
    const btns = wrapper.findAll('button')
    const confirmBtn = btns.find((b) => b.text().includes('Confirm and import'))
    await confirmBtn!.trigger('click')
    expect(wrapper.emitted('confirm')).toBeTruthy()
  })

  it('emits goBack event', async () => {
    const wrapper = mount(CvImportPreview, { props: { preview: makePreview(), isPending: false } })
    const btns = wrapper.findAll('button')
    const backBtn = btns.find((b) => b.text().includes('Back to review'))
    await backBtn!.trigger('click')
    expect(wrapper.emitted('goBack')).toBeTruthy()
  })

  it('shows error when preview is null and not pending', () => {
    const wrapper = mount(CvImportPreview, { props: { preview: null, isPending: false } })
    expect(wrapper.text()).toContain('Unable to load import preview')
  })
})
