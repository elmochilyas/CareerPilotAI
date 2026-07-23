import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReorderControls from '@/features/profile/components/ReorderControls.vue'

describe('ReorderControls', () => {
  it('emits up when up button clicked', async () => {
    const wrapper = mount(ReorderControls, { props: { first: false, last: false } })
    await wrapper.find('button[aria-label="Move up"]').trigger('click')
    expect(wrapper.emitted('up')).toBeTruthy()
  })

  it('emits down when down button clicked', async () => {
    const wrapper = mount(ReorderControls, { props: { first: false, last: false } })
    await wrapper.find('button[aria-label="Move down"]').trigger('click')
    expect(wrapper.emitted('down')).toBeTruthy()
  })

  it('disables up button when first', () => {
    const wrapper = mount(ReorderControls, { props: { first: true, last: false } })
    const upBtn = wrapper.find('button[aria-label="Move up"]')
    expect(upBtn.attributes('disabled')).toBeDefined()
  })

  it('disables down button when last', () => {
    const wrapper = mount(ReorderControls, { props: { first: false, last: true } })
    const downBtn = wrapper.find('button[aria-label="Move down"]')
    expect(downBtn.attributes('disabled')).toBeDefined()
  })

  it('renders both buttons', () => {
    const wrapper = mount(ReorderControls, { props: { first: false, last: false } })
    expect(wrapper.findAll('button')).toHaveLength(2)
  })

  it('has accessible labels', () => {
    const wrapper = mount(ReorderControls, { props: { first: false, last: false } })
    expect(wrapper.find('button[aria-label="Move up"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Move down"]').exists()).toBe(true)
  })

  it('renders navigation landmark', () => {
    const wrapper = mount(ReorderControls, { props: { first: false, last: false } })
    expect(wrapper.find('nav[aria-label="Reorder item"]').exists()).toBe(true)
  })
})
