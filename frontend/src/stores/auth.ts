import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import type { User, LoginCredentials, RegisterData } from '@/features/auth/types'
import {
  fetchCsrfCookie,
  loginUser,
  logoutUser,
  fetchCurrentUser,
  registerUser,
} from '@/features/auth/api'
import { setUnauthorizedHandler } from '@/api/client/axios'

const SESSION_HINT_KEY = 'auth:active_session'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const initializing = ref(false)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isEmailVerified = computed(() => user.value?.email_verified_at !== null)

  setUnauthorizedHandler(() => {
    user.value = null
    localStorage.removeItem(SESSION_HINT_KEY)
  })

  let initializePromise: Promise<void> | null = null

  async function initialize(): Promise<void> {
    if (initialized.value) return
    if (initializePromise) return initializePromise

    initializePromise = (async () => {
      initializing.value = true
      try {
        if (!localStorage.getItem(SESSION_HINT_KEY)) {
          user.value = null
          return
        }
        const response = await fetchCurrentUser()
        user.value = response.data
      } catch {
        user.value = null
        localStorage.removeItem(SESSION_HINT_KEY)
      } finally {
        initializing.value = false
        initialized.value = true
      }
    })()

    return initializePromise
  }

  async function login(credentials: LoginCredentials): Promise<User> {
    loading.value = true
    try {
      await fetchCsrfCookie()
      const response = await loginUser(credentials)
      user.value = response.data
      localStorage.setItem(SESSION_HINT_KEY, '1')
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function register(data: RegisterData): Promise<User> {
    loading.value = true
    try {
      await fetchCsrfCookie()
      const response = await registerUser(data)
      user.value = response.data
      localStorage.setItem(SESSION_HINT_KEY, '1')
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    loading.value = true
    try {
      await fetchCsrfCookie()
      await logoutUser()
    } catch {
      // Ignore server errors (e.g. 401 for already-expired session)
    } finally {
      user.value = null
      localStorage.removeItem(SESSION_HINT_KEY)
      loading.value = false
    }
  }

  async function fetchUser(): Promise<void> {
    try {
      const response = await fetchCurrentUser()
      user.value = response.data
    } catch {
      user.value = null
    }
  }

  function clear(): void {
    user.value = null
    localStorage.removeItem(SESSION_HINT_KEY)
  }

  return {
    user,
    loading,
    initializing,
    initialized,
    isAuthenticated,
    isEmailVerified,
    initialize,
    login,
    register,
    logout,
    fetchUser,
    clear,
  }
})
