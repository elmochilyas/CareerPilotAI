import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import App from '../App.vue'
import { createRouter, createWebHistory } from 'vue-router'
import { createPinia } from 'pinia'

describe('App', () => {
  it('renders without errors', () => {
    const router = createRouter({
      history: createWebHistory(),
      routes: [],
    })
    const pinia = createPinia()
    const wrapper = mount(App, {
      global: { plugins: [router, pinia] },
    })
    expect(wrapper.exists()).toBe(true)
  })
})
