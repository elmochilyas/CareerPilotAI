import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '@/features/auth/pages/LoginPage.vue'

vi.mock('@/features/auth/api', () => ({
  fetchCsrfCookie: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
  loginUser: vi.fn<() => Promise<unknown>>(),
}))

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
    { path: '/register', name: 'register', component: { template: '<div>Register</div>' } },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: { template: '<div>Forgot Password</div>' },
    },
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
  ],
})

function createWrapper() {
  return mount(LoginPage, {
    global: {
      plugins: [router, createPinia()],
    },
  })
}

describe('LoginPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders the login form', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('h1').text()).toContain('Sign in')
    expect(wrapper.find('#email').exists()).toBe(true)
    expect(wrapper.find('#password').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('has link to register page', () => {
    const wrapper = createWrapper()
    expect(wrapper.text()).toContain('Register')
  })

  it('has link to forgot password page', () => {
    const wrapper = createWrapper()
    expect(wrapper.text()).toContain('Forgot password')
  })

  it('calls login API on form submit', async () => {
    const { loginUser } = await import('@/features/auth/api')
    vi.mocked(loginUser).mockResolvedValueOnce({
      data: {
        id: 1,
        full_name: 'Jane',
        email: 'jane@example.com',
        email_verified_at: null,
        role: 'candidate',
        account_status: 'active',
        timezone: 'UTC',
        created_at: '',
        updated_at: '',
      },
    })

    const wrapper = createWrapper()
    await wrapper.find('#email').setValue('jane@example.com')
    await wrapper.find('#password').setValue('secret123')
    await wrapper.find('form').trigger('submit.prevent')

    await vi.waitFor(() => {
      expect(vi.mocked(loginUser)).toHaveBeenCalledWith({
        email: 'jane@example.com',
        password: 'secret123',
      })
    })
  })
})
