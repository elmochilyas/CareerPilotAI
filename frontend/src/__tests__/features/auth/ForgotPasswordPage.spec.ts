import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import ForgotPasswordPage from '@/features/auth/pages/ForgotPasswordPage.vue'

vi.mock('@/features/auth/api', () => ({
  fetchCsrfCookie: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
  sendForgotPasswordLink: vi.fn<() => Promise<unknown>>(),
}))

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: { template: '<div>Forgot Password</div>' },
    },
    { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
  ],
})

function createWrapper() {
  return mount(ForgotPasswordPage, {
    global: {
      plugins: [router, createPinia()],
    },
  })
}

describe('ForgotPasswordPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders the forgot password form', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('h1').text()).toContain('Reset your password')
    expect(wrapper.find('#email').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('shows success message after form submission', async () => {
    const { sendForgotPasswordLink } = await import('@/features/auth/api')
    vi.mocked(sendForgotPasswordLink).mockResolvedValueOnce({ message: 'Reset link sent.' })

    const wrapper = createWrapper()
    await wrapper.find('#email').setValue('jane@example.com')
    await wrapper.find('form').trigger('submit.prevent')

    await vi.waitFor(() => {
      expect(wrapper.text()).toContain('If that email exists')
    })
  })

  it('has link to login page', () => {
    const wrapper = createWrapper()
    expect(wrapper.text()).toContain('Back to sign in')
  })
})
