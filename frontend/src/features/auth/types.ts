export interface User {
  id: number
  full_name: string
  email: string
  email_verified_at: string | null
  role: string
  account_status: string
  timezone: string
  created_at: string
  updated_at: string
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterData {
  full_name: string
  email: string
  password: string
  password_confirmation: string
}

export interface ForgotPasswordData {
  email: string
}

export interface ResetPasswordData {
  email: string
  token: string
  password: string
  password_confirmation: string
}
