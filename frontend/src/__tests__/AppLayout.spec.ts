import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppLayout from '@/components/ui/AppLayout.vue'
import { RouterLinkStub } from '@vue/test-utils'

describe('AppLayout', () => {
  it('renders skip link and nav', () => {
    const wrapper = mount(AppLayout, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
      slots: { default: '<p>content</p>' },
    })

    expect(wrapper.find('a').text()).toBe('Skip to content')
    expect(wrapper.find('nav').exists()).toBe(true)
    expect(wrapper.find('main').exists()).toBe(true)
    expect(wrapper.find('main').text()).toContain('content')
  })
})
