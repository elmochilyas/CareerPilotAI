import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ResumePreview from '@/features/cv-tailoring/components/ResumePreview.vue'
import type { Resume } from '@/features/cv-tailoring/types'

const resume: Resume = {
  id: 1,
  candidate_profile_id: 1,
  opportunity_id: 1,
  title: 'Backend Developer CV',
  template_key: null,
  content: { headline: null, summary: null, sections: [] },
  status: 'draft',
  generated_by: 'manual',
  stale: false,
  stale_reason: null,
  version_no: 1,
  approved_at: null,
  created_at: '2026-08-10T00:00:00Z',
  updated_at: '2026-08-10T00:00:00Z',
}

describe('ResumePreview', () => {
  it('shows a generation action instead of a blank panel for an empty preview', async () => {
    const wrapper = mount(ResumePreview, {
      props: {
        resume,
        preview: { headline: null, summary: null, sections: [] },
        isLoading: false,
        isGenerating: false,
        generationFailed: false,
      },
    })

    expect(wrapper.text()).toContain('Your tailored CV hasn’t been generated yet.')

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('generate')).toHaveLength(1)
  })

  it('renders ready preview content', () => {
    const wrapper = mount(ResumePreview, {
      props: {
        resume,
        preview: {
          headline: 'PHP Backend Developer',
          summary: 'Builds reliable Laravel APIs.',
          sections: [
            {
              type: 'experience',
              title: 'Experience',
              has_changes: false,
              items: [
                {
                  source_ref: 'profile_item:1',
                  original_text: 'Built Laravel APIs.',
                  current_text: 'Built Laravel APIs.',
                  ai_proposals: [],
                },
              ],
            },
          ],
        },
        isLoading: false,
        isGenerating: false,
        generationFailed: false,
        showActions: true,
      },
    })

    expect(wrapper.text()).toContain('PHP Backend Developer')
    expect(wrapper.text()).toContain('Builds reliable Laravel APIs.')
    expect(wrapper.text()).toContain('Built Laravel APIs.')
    expect(wrapper.text()).toContain('Continue to Review')
  })
})
