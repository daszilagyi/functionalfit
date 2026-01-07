// Admin API client for group class occurrences management
import apiClient from '../client'
import type { ApiResponse } from '@/types/api'
import type { ClassOccurrence } from '@/types/class'

export interface CreateClassOccurrenceRequest {
  class_template_id: string
  room_id: string
  trainer_id: string
  starts_at: string
  ends_at: string
  max_capacity: number
  credits_required?: number
  is_recurring?: boolean
  repeat_from?: string
  repeat_until?: string
}

export interface UpdateClassOccurrenceRequest {
  starts_at?: string
  ends_at?: string
  room_id?: string
  trainer_id?: string
  capacity?: number
  status?: 'scheduled' | 'completed' | 'cancelled'
  force_override?: boolean
}

export const adminClassOccurrencesApi = {
  /**
   * List all class occurrences (admin)
   */
  list: async (filters?: {
    date_from?: string
    date_to?: string
    status?: string
  }): Promise<ClassOccurrence[]> => {
    const response = await apiClient.get<ApiResponse<ClassOccurrence[]>>(
      '/admin/class-occurrences',
      { params: filters }
    )
    return response.data.data
  },

  /**
   * Get single class occurrence (admin)
   */
  get: async (id: string): Promise<ClassOccurrence> => {
    const response = await apiClient.get<ApiResponse<ClassOccurrence>>(
      `/admin/class-occurrences/${id}`
    )
    return response.data.data
  },

  /**
   * Create new class occurrence (admin)
   */
  create: async (data: CreateClassOccurrenceRequest): Promise<ClassOccurrence> => {
    const response = await apiClient.post<ApiResponse<ClassOccurrence>>(
      '/admin/class-occurrences',
      data
    )
    return response.data.data
  },

  /**
   * Update a class occurrence (admin)
   */
  update: async (id: string, data: UpdateClassOccurrenceRequest): Promise<ClassOccurrence> => {
    const response = await apiClient.patch<ApiResponse<ClassOccurrence>>(
      `/admin/class-occurrences/${id}`,
      data
    )
    return response.data.data
  },

  /**
   * Force move/reschedule a class occurrence (admin override)
   */
  forceMove: async (id: string, data: UpdateClassOccurrenceRequest): Promise<ClassOccurrence> => {
    const response = await apiClient.patch<ApiResponse<ClassOccurrence>>(
      `/admin/class-occurrences/${id}/force-move`,
      data
    )
    return response.data.data
  },

  /**
   * Cancel a class occurrence (admin)
   */
  delete: async (id: string): Promise<void> => {
    await apiClient.delete(`/admin/class-occurrences/${id}`)
  },
}

// React Query keys factory for admin class occurrences
export const adminClassOccurrenceKeys = {
  all: ['admin', 'class-occurrences'] as const,
  lists: () => [...adminClassOccurrenceKeys.all, 'list'] as const,
  list: (filters?: object) => [...adminClassOccurrenceKeys.lists(), filters] as const,
  details: () => [...adminClassOccurrenceKeys.all, 'detail'] as const,
  detail: (id: string) => [...adminClassOccurrenceKeys.details(), id] as const,
}
