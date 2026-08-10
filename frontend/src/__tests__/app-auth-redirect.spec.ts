import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import App from '@/App.vue'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/features/auth/api', () => ({
  fetchCsrfCookie: vi.fn<(...args: unknown[]) => unknown>(),
  loginUser: vi.fn<(...args: unknown[]) => unknown>(),
  logoutUser: vi.fn<(...args: unknown[]) => unknown>(),
  fetchCurrentUser: vi.fn<(...args: unknown[]) => unknown>(),
  registerUser: vi.fn<(...args: unknown[]) => unknown>(),
}))

describe('App authentication state', () => {
  it('redirects an expired protected route to login and preserves the destination', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
        {
          path: '/opportunities/:id/tailor',
          name: 'opportunities-tailor',
          meta: { requiresAuth: true },
          component: { template: '<div>Tailor</div>' },
        },
      ],
    })

    await router.push('/opportunities/1/tailor')
    await router.isReady()

    const auth = useAuthStore()
    auth.initialized = true
    auth.user = {
      id: 1,
      full_name: 'Test Candidate',
      email: 'candidate@example.com',
      email_verified_at: '2026-08-09T00:00:00Z',
      role: 'candidate',
      account_status: 'active',
      timezone: 'Africa/Casablanca',
      created_at: '2026-08-09T00:00:00Z',
      updated_at: '2026-08-09T00:00:00Z',
    }

    mount(App, { global: { plugins: [pinia, router] } })
    auth.clear()
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/opportunities/1/tailor')
  })
})
