import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import RegisterPage from '@/features/auth/pages/RegisterPage.vue'

vi.mock('@/features/auth/api', () => ({
  fetchCsrfCookie: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
  registerUser: vi.fn<() => Promise<unknown>>(),
}))

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/register', name: 'register', component: { template: '<div>Register</div>' } },
    { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
  ],
})

function createWrapper() {
  return mount(RegisterPage, {
    global: {
      plugins: [router, createPinia()],
    },
  })
}

describe('RegisterPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders the registration form', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('h1').text()).toContain('Create your account')
    expect(wrapper.find('#full_name').exists()).toBe(true)
    expect(wrapper.find('#email').exists()).toBe(true)
    expect(wrapper.find('#password').exists()).toBe(true)
    expect(wrapper.find('#password_confirmation').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('has link to login page', () => {
    const wrapper = createWrapper()
    expect(wrapper.text()).toContain('Sign in')
  })

  it('calls register API on form submit', async () => {
    const { registerUser } = await import('@/features/auth/api')
    vi.mocked(registerUser).mockResolvedValueOnce({
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
    await wrapper.find('#full_name').setValue('Jane Doe')
    await wrapper.find('#email').setValue('jane@example.com')
    await wrapper.find('#password').setValue('secret123')
    await wrapper.find('#password_confirmation').setValue('secret123')
    await wrapper.find('form').trigger('submit.prevent')

    await vi.waitFor(() => {
      expect(vi.mocked(registerUser)).toHaveBeenCalledWith({
        full_name: 'Jane Doe',
        email: 'jane@example.com',
        password: 'secret123',
        password_confirmation: 'secret123',
      })
    })
  })
})
