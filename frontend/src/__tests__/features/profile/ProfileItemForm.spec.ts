import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileItemForm from '@/features/profile/components/ProfileItemForm.vue'

const stubs = { Teleport: true }

describe('ProfileItemForm', () => {
  it('renders nothing when closed', () => {
    const wrapper = mount(ProfileItemForm, {
      props: { open: false, type: 'experience', saving: false },
      global: { stubs },
    })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('renders dialog when open', () => {
    const wrapper = mount(ProfileItemForm, {
      props: { open: true, type: 'education', saving: false },
      global: { stubs },
    })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Add education')
  })

  it('shows Edit title when editing an existing item', () => {
    const wrapper = mount(ProfileItemForm, {
      props: {
        open: true,
        type: 'project',
        item: {
          id: 1,
          type: 'project',
          title: 'Existing',
          organization: null,
          location: null,
          start_date: null,
          end_date: null,
          description: null,
          metadata: null,
          display_order: 0,
          created_at: '',
          updated_at: '',
        },
        saving: false,
      },
      global: { stubs },
    })
    expect(wrapper.text()).toContain('Edit project')
  })

  it('emits cancel on cancel button click', async () => {
    const wrapper = mount(ProfileItemForm, {
      props: { open: true, type: 'experience', saving: false },
      global: { stubs },
    })
    await wrapper.find('button[type="button"]').trigger('click')
    expect(wrapper.emitted('cancel')).toBeTruthy()
  })

  it('emits save with form values on submit', async () => {
    const wrapper = mount(ProfileItemForm, {
      props: { open: true, type: 'experience', saving: false },
      global: { stubs },
    })
    const titleInput = wrapper.find('input[name="title"]')
    await titleInput.setValue('Software Engineer')
    const orgInput = wrapper.find('input[name="organization"]')
    await orgInput.setValue('Acme Corp')
    await wrapper.find('form').trigger('submit.prevent')
    expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
      type: 'experience',
      title: 'Software Engineer',
      organization: 'Acme Corp',
    })
  })

  it('disables save button while saving', () => {
    const wrapper = mount(ProfileItemForm, {
      props: { open: true, type: 'experience', saving: true },
      global: { stubs },
    })
    const saveBtn = wrapper.findAll('button').find((b) => b.text().includes('Saving'))
    expect(saveBtn?.attributes('disabled')).toBeDefined()
  })

  it('requires title via native validation', () => {
    const wrapper = mount(ProfileItemForm, {
      props: { open: true, type: 'experience', saving: false },
      global: { stubs },
    })
    const titleInput = wrapper.find('input[name="title"]')
    expect(titleInput.attributes('required')).toBeDefined()
  })

  it('pre-fills values when editing an existing item', () => {
    const wrapper = mount(ProfileItemForm, {
      props: {
        open: true,
        type: 'experience',
        item: {
          id: 1,
          type: 'experience',
          title: 'Engineer',
          organization: 'Co',
          location: 'NYC',
          start_date: '2020-01-01',
          end_date: '2023-01-01',
          description: 'Did things',
          metadata: null,
          display_order: 0,
          created_at: '',
          updated_at: '',
        },
        saving: false,
      },
      global: { stubs },
    })
    const titleInput = wrapper.find('input[name="title"]').element as HTMLInputElement
    expect(titleInput.value).toBe('Engineer')
  })
})
