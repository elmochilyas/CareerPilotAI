import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

describe('axios-interceptor', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('auth store clear function clears user and hint', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      full_name: 'Jane',
      email: 'jane@example.com',
      email_verified_at: null,
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '',
      updated_at: '',
    }

    localStorage.setItem('auth:active_session', '1')
    expect(auth.isAuthenticated).toBe(true)

    auth.clear()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
    expect(localStorage.getItem('auth:active_session')).toBeNull()
  })
})
