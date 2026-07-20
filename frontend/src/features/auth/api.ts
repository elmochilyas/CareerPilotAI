import client from '@/api/client/axios'
import type {
  ForgotPasswordData,
  LoginCredentials,
  RegisterData,
  ResetPasswordData,
  User,
} from './types'

export function fetchCsrfCookie(): Promise<void> {
  return client.get('/sanctum/csrf-cookie')
}

export function registerUser(data: RegisterData): Promise<{ data: User }> {
  return client.post('/api/v1/auth/register', data).then((res) => res.data)
}

export function loginUser(data: LoginCredentials): Promise<{ data: User }> {
  return client.post('/api/v1/auth/login', data).then((res) => res.data)
}

export function logoutUser(): Promise<void> {
  return client.delete('/api/v1/auth/logout')
}

export function fetchCurrentUser(): Promise<{ data: User }> {
  return client.get('/api/v1/me', { validateStatus: (status) => status < 500 }).then((res) => {
    if (res.status === 401) {
      throw new Error('Unauthenticated')
    }
    return res.data
  })
}

export function sendForgotPasswordLink(data: ForgotPasswordData): Promise<{ message: string }> {
  return client.post('/api/v1/auth/forgot-password', data).then((res) => res.data)
}

export function resetPassword(data: ResetPasswordData): Promise<{ message: string }> {
  return client.post('/api/v1/auth/reset-password', data).then((res) => res.data)
}

export function resendVerificationEmail(): Promise<{ message: string }> {
  return client.post('/api/v1/email/verification-notification').then((res) => res.data)
}
