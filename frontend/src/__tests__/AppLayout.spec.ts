import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/components/ui/AppLayout.vue'
import { RouterLinkStub } from '@vue/test-utils'

const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', name: 'home', component: { template: '<div>home</div>' } }],
})

describe('AppLayout', () => {
  it('renders skip link and nav', () => {
    const wrapper = mount(AppLayout, {
      global: {
        plugins: [createPinia(), router],
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
