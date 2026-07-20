import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/features/auth/api', () => ({
  fetchCsrfCookie: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
  loginUser: vi.fn<() => Promise<unknown>>(),
  logoutUser: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
  fetchCurrentUser: vi.fn<() => Promise<unknown>>(),
  registerUser: vi.fn<() => Promise<unknown>>(),
  sendForgotPasswordLink: vi.fn<() => Promise<unknown>>(),
  resetPassword: vi.fn<() => Promise<unknown>>(),
  resendVerificationEmail: vi.fn<() => Promise<unknown>>(),
}))

describe('auth-store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    localStorage.clear()
  })

  it('is not authenticated by default', () => {
    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
  })

  it('is not initialized by default', () => {
    const auth = useAuthStore()
    expect(auth.initialized).toBe(false)
    expect(auth.initializing).toBe(false)
  })

  it('login sets user and session hint', async () => {
    const auth = useAuthStore()
    const { loginUser } = await import('@/features/auth/api')

    localStorage.removeItem('auth:active_session')

    const userData = {
      id: 1,
      full_name: 'Jane Doe',
      email: 'jane@example.com',
      email_verified_at: null,
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    vi.mocked(loginUser).mockResolvedValueOnce({ data: userData })

    const result = await auth.login({ email: 'jane@example.com', password: 'secret' })

    expect(result).toEqual(userData)
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user).toEqual(userData)
    expect(localStorage.getItem('auth:active_session')).toBe('1')
  })

  it('logout clears user and session hint', async () => {
    const auth = useAuthStore()
    const userData = {
      id: 1,
      full_name: 'Jane Doe',
      email: 'jane@example.com',
      email_verified_at: null,
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    localStorage.setItem('auth:active_session', '1')
    auth.user = userData
    await auth.logout()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
    expect(localStorage.getItem('auth:active_session')).toBeNull()
  })

  it('logout clears user and hint even when API returns 401', async () => {
    const auth = useAuthStore()
    const { logoutUser } = await import('@/features/auth/api')

    const userData = {
      id: 1,
      full_name: 'Jane Doe',
      email: 'jane@example.com',
      email_verified_at: null,
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    localStorage.setItem('auth:active_session', '1')
    auth.user = userData
    vi.mocked(logoutUser).mockRejectedValueOnce(new Error('Request failed with status code 401'))
    await auth.logout()

    expect(auth.user).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem('auth:active_session')).toBeNull()
  })

  it('fetchCurrentUser sets user on fetchUser', async () => {
    const auth = useAuthStore()
    const { fetchCurrentUser } = await import('@/features/auth/api')

    const userData = {
      id: 1,
      full_name: 'Jane Doe',
      email: 'jane@example.com',
      email_verified_at: null,
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    vi.mocked(fetchCurrentUser).mockResolvedValueOnce({ data: userData })
    await auth.fetchUser()

    expect(auth.isAuthenticated).toBe(true)

    vi.mocked(fetchCurrentUser).mockRejectedValueOnce(new Error('Unauthenticated'))
    await auth.fetchUser()

    expect(auth.isAuthenticated).toBe(false)
  })

  it('initialize skips API call when no session hint', async () => {
    const auth = useAuthStore()
    const { fetchCurrentUser } = await import('@/features/auth/api')

    localStorage.removeItem('auth:active_session')
    vi.mocked(fetchCurrentUser).mockResolvedValueOnce({ data: { id: 1 } })
    await auth.initialize()

    expect(fetchCurrentUser).not.toHaveBeenCalled()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.initialized).toBe(true)
  })

  it('initialize restores user from /api/v1/me when hint exists', async () => {
    const auth = useAuthStore()
    const { fetchCurrentUser } = await import('@/features/auth/api')

    localStorage.setItem('auth:active_session', '1')

    const userData = {
      id: 1,
      full_name: 'Jane Doe',
      email: 'jane@example.com',
      email_verified_at: null,
      role: 'candidate',
      account_status: 'active',
      timezone: 'UTC',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    vi.mocked(fetchCurrentUser).mockResolvedValueOnce({ data: userData })
    await auth.initialize()

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user).toEqual(userData)
    expect(auth.initialized).toBe(true)
    expect(auth.initializing).toBe(false)
  })

  it('initialize handles 401 as guest when hint exists', async () => {
    const auth = useAuthStore()
    const { fetchCurrentUser } = await import('@/features/auth/api')

    localStorage.setItem('auth:active_session', '1')
    vi.mocked(fetchCurrentUser).mockRejectedValueOnce(new Error('Unauthenticated'))
    await auth.initialize()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
    expect(auth.initialized).toBe(true)
    expect(auth.initializing).toBe(false)
    expect(localStorage.getItem('auth:active_session')).toBeNull()
  })

  it('initialize is idempotent when called multiple times', async () => {
    const auth = useAuthStore()
    const { fetchCurrentUser } = await import('@/features/auth/api')

    localStorage.setItem('auth:active_session', '1')
    vi.mocked(fetchCurrentUser).mockRejectedValueOnce(new Error('Unauthenticated'))
    await auth.initialize()
    await auth.initialize()

    expect(fetchCurrentUser).toHaveBeenCalledTimes(1)
    expect(auth.initialized).toBe(true)
  })
})
