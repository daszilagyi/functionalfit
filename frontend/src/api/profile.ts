import apiClient from './client'
import type { ApiResponse } from '@/types/api'

export interface UserProfile {
  id: number
  name: string
  email: string
  phone: string | null
  role: 'admin' | 'staff' | 'client'
  // Client-specific fields
  date_of_birth?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  // Staff-specific fields
  bio?: string | null
  specialization?: string | null
}

export interface UpdateProfileRequest {
  name?: string
  email?: string
  phone?: string | null
  // Client-specific fields
  date_of_birth?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  // Staff-specific fields
  bio?: string | null
  specialization?: string | null
}

export interface ChangePasswordRequest {
  current_password: string
  new_password: string
  new_password_confirmation: string
}

// Query keys factory
export const profileKeys = {
  all: ['profile'] as const,
  current: () => [...profileKeys.all, 'current'] as const,
}

export const profileApi = {
  /**
   * Get current user's profile
   */
  get: async (): Promise<UserProfile> => {
    const response = await apiClient.get<ApiResponse<UserProfile>>('/profile')
    return response.data.data
  },

  /**
   * Update current user's profile
   */
  update: async (data: UpdateProfileRequest): Promise<UserProfile> => {
    const response = await apiClient.patch<ApiResponse<UserProfile>>('/profile', data)
    return response.data.data
  },

  /**
   * Change current user's password
   */
  changePassword: async (data: ChangePasswordRequest): Promise<void> => {
    await apiClient.post('/profile/change-password', data)
  },
}
