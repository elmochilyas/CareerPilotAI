import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SectionEditWrapper from '@/features/profile/components/SectionEditWrapper.vue'

describe('SectionEditWrapper', () => {
  it('renders section title', () => {
    const wrapper = mount(SectionEditWrapper, {
      props: { title: 'Experience', editing: false },
      slots: { default: '<p>Content</p>' },
    })
    expect(wrapper.text()).toContain('Experience')
  })

  it('shows Edit button when not editing', () => {
    const wrapper = mount(SectionEditWrapper, {
      props: { title: 'Summary', editing: false },
      slots: { default: '<p>Content</p>' },
    })
    expect(wrapper.text()).toContain('Edit')
    expect(wrapper.text()).not.toContain('Cancel')
  })

  it('shows Cancel button when editing', () => {
    const wrapper = mount(SectionEditWrapper, {
      props: { title: 'Summary', editing: true },
      slots: { default: '<p>Content</p>' },
    })
    expect(wrapper.text()).toContain('Cancel')
    expect(wrapper.text()).not.toContain('Edit')
  })

  it('emits edit on Edit button click', async () => {
    const wrapper = mount(SectionEditWrapper, {
      props: { title: 'Summary', editing: false },
      slots: { default: '<p>Content</p>' },
    })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('edit')).toBeTruthy()
  })

  it('emits cancel on Cancel button click', async () => {
    const wrapper = mount(SectionEditWrapper, {
      props: { title: 'Summary', editing: true },
      slots: { default: '<p>Content</p>' },
    })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('cancel')).toBeTruthy()
  })

  it('renders slot content', () => {
    const wrapper = mount(SectionEditWrapper, {
      props: { title: 'Summary', editing: false },
      slots: { default: '<span data-test="slot">Custom content</span>' },
    })
    expect(wrapper.find('[data-test="slot"]').text()).toBe('Custom content')
  })
})
