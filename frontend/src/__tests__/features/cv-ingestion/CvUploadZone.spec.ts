import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvUploadZone from '@/features/cv-ingestion/components/CvUploadZone.vue'

describe('CvUploadZone', () => {
  it('renders upload prompt text', () => {
    const wrapper = mount(CvUploadZone)
    expect(wrapper.text()).toContain('Drop your CV here or click to browse')
  })

  it('shows accepted file types', () => {
    const wrapper = mount(CvUploadZone)
    expect(wrapper.text()).toContain('PDF or DOCX')
  })

  it('has an accessible label on the drop zone', () => {
    const wrapper = mount(CvUploadZone)
    const zone = wrapper.find('[role="button"]')
    expect(zone.attributes('aria-label')).toContain('Upload your CV')
  })

  it('shows file summary after a file is selected via input', async () => {
    const wrapper = mount(CvUploadZone)
    const file = new File(['dummy content'], 'resume.pdf', { type: 'application/pdf' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(wrapper.text()).toContain('resume.pdf')
  })

  it('shows error for disallowed file type', async () => {
    const wrapper = mount(CvUploadZone)
    const file = new File(['dummy'], 'image.png', { type: 'image/png' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(wrapper.text()).toContain('Only PDF and DOCX files are supported')
  })

  it('shows Upload CV button after file selection', async () => {
    const wrapper = mount(CvUploadZone)
    const file = new File(['dummy content'], 'resume.pdf', { type: 'application/pdf' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(wrapper.text()).toContain('Upload CV')
  })

  it('emits fileSelected event on confirm', async () => {
    const wrapper = mount(CvUploadZone)
    const file = new File(['dummy content'], 'resume.pdf', { type: 'application/pdf' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    const buttons = wrapper.findAll('button')
    const uploadBtn = buttons.find((b) => b.text().includes('Upload CV'))
    await uploadBtn!.trigger('click')
    expect(wrapper.emitted('fileSelected')).toBeTruthy()
    expect(wrapper.emitted('fileSelected')![0]).toEqual([file])
  })

  it('allows clearing the selected file', async () => {
    const wrapper = mount(CvUploadZone)
    const file = new File(['dummy content'], 'resume.pdf', { type: 'application/pdf' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(wrapper.text()).toContain('resume.pdf')
    const clearBtn = wrapper.find('[aria-label="Remove selected file"]')
    await clearBtn.trigger('click')
    expect(wrapper.text()).not.toContain('resume.pdf')
  })
})
