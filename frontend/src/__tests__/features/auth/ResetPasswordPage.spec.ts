import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import ResetPasswordPage from '@/features/auth/pages/ResetPasswordPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/reset-password',
      name: 'reset-password',
      component: { template: '<div>Reset Password</div>' },
    },
    { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
  ],
})

function createWrapper() {
  return mount(ResetPasswordPage, {
    global: {
      plugins: [router, createPinia()],
    },
  })
}

describe('ResetPasswordPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders the reset password form', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('h1').text()).toContain('Set new password')
    expect(wrapper.find('#password').exists()).toBe(true)
    expect(wrapper.find('#password_confirmation').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('has link to login page', () => {
    const wrapper = createWrapper()
    expect(wrapper.text()).toContain('Back to sign in')
  })
})
