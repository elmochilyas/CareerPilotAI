import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ResumeDocumentPreview from '@/features/cv-tailoring/components/ResumeDocumentPreview.vue'

describe('ResumeDocumentPreview', () => {
  it('loads the authenticated shared document renderer for the resume', async () => {
    const wrapper = mount(ResumeDocumentPreview, {
      props: { resumeId: 12 },
    })

    const frame = wrapper.get('iframe')
    expect(frame.attributes('src')).toBe('/api/v1/resumes/12/document-preview')
    expect(frame.attributes('title')).toBe('Final tailored CV preview')
    expect(wrapper.text()).toContain('Loading the final CV document')

    await frame.trigger('load')

    expect(wrapper.text()).not.toContain('Loading the final CV document')
  })
})
