import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import NavBar from '@/components/ui/NavBar.vue'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/features/auth/api', () => ({
  fetchCsrfCookie: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
  logoutUser: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
  fetchCurrentUser: vi.fn<() => Promise<unknown>>(),
  loginUser: vi.fn<() => Promise<unknown>>(),
  registerUser: vi.fn<() => Promise<unknown>>(),
  sendForgotPasswordLink: vi.fn<() => Promise<unknown>>(),
  resetPassword: vi.fn<() => Promise<unknown>>(),
  resendVerificationEmail: vi.fn<() => Promise<unknown>>(),
}))

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div>home</div>' } },
    { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
  ],
})

function createWrapper() {
  const pinia = createPinia()
  setActivePinia(pinia)
  return mount(NavBar, {
    global: {
      plugins: [pinia, router],
      stubs: { RouterLink: RouterLinkStub },
    },
  })
}

describe('NavBar', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows Login and Register links when guest', () => {
    const wrapper = createWrapper()
    expect(wrapper.text()).toContain('Login')
    expect(wrapper.text()).toContain('Register')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('shows email and Logout button when authenticated', async () => {
    const wrapper = createWrapper()
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      full_name: 'Jane Doe',
      email: 'jane@example.com',
      email_verified_at: '2026-01-01T00:00:00Z',
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('jane@example.com')
    expect(wrapper.text()).toContain('Logout')
    expect(wrapper.text()).not.toContain('Login')
    expect(wrapper.text()).not.toContain('Register')
  })

  it('disables Logout button while logout is in progress', async () => {
    const wrapper = createWrapper()
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      full_name: 'Jane Doe',
      email: 'jane@example.com',
      email_verified_at: '2026-01-01T00:00:00Z',
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    await wrapper.vm.$nextTick()

    auth.loading = true
    await wrapper.vm.$nextTick()

    const button = wrapper.find('button')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.text()).toContain('Logging out...')
  })
})
