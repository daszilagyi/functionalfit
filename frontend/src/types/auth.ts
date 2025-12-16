import { type User } from './user'

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}

export interface AuthState {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
  error: string | null
}

export interface AuthResponse {
  user: User
  token?: string // Optional API token (if not using cookie-based auth)
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  phone?: string
}
