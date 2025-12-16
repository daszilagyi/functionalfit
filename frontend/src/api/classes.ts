// Class booking API client functions
import apiClient from './client'
import type { ApiResponse } from '@/types/api'
import type {
  ClassOccurrence,
  ClassListFilters,
  BookClassRequest,
  BookClassResponse,
  CancelBookingRequest,
  CancelBookingResponse,
  ClassRegistration,
} from '@/types/class'

export const classesApi = {
  /**
   * List all class occurrences with optional filters
   */
  list: async (filters?: ClassListFilters): Promise<ClassOccurrence[]> => {
    const response = await apiClient.get<ApiResponse<ClassOccurrence[]>>('/classes', {
      params: filters,
    })
    return response.data.data
  },

  /**
   * Get single class occurrence details
   */
  get: async (occurrenceId: string): Promise<ClassOccurrence> => {
    const response = await apiClient.get<ApiResponse<ClassOccurrence>>(
      `/classes/${occurrenceId}`
    )
    return response.data.data
  },

  /**
   * Book a class (joins waitlist if full)
   */
  book: async (
    occurrenceId: string,
    data: BookClassRequest
  ): Promise<BookClassResponse> => {
    const response = await apiClient.post<ApiResponse<BookClassResponse>>(
      `/classes/${occurrenceId}/book`,
      data
    )
    return response.data.data
  },

  /**
   * Cancel a class booking (24h window check on backend)
   */
  cancel: async (
    occurrenceId: string,
    data?: CancelBookingRequest
  ): Promise<CancelBookingResponse> => {
    const response = await apiClient.post<ApiResponse<CancelBookingResponse>>(
      `/classes/${occurrenceId}/cancel`,
      data
    )
    return response.data.data
  },

  /**
   * Get my registrations for a specific class occurrence
   */
  getMyRegistration: async (occurrenceId: string): Promise<ClassRegistration | null> => {
    const response = await apiClient.get<ApiResponse<ClassRegistration | null>>(
      `/classes/${occurrenceId}/my-registration`
    )
    return response.data.data
  },

  /**
   * Update class occurrence (admin only - force move)
   */
  updateOccurrence: async (
    occurrenceId: string,
    data: { starts_at: string; ends_at: string; room_id?: number; trainer_id?: number }
  ): Promise<ClassOccurrence> => {
    const response = await apiClient.patch<ApiResponse<ClassOccurrence>>(
      `/admin/class-occurrences/${occurrenceId}/force-move`,
      data
    )
    return response.data.data
  },
}

// React Query keys factory for classes
export const classKeys = {
  all: ['classes'] as const,
  lists: () => [...classKeys.all, 'list'] as const,
  list: (filters?: ClassListFilters) => [...classKeys.lists(), filters] as const,
  details: () => [...classKeys.all, 'detail'] as const,
  detail: (id: string) => [...classKeys.details(), id] as const,
  registration: (id: string) => [...classKeys.all, 'registration', id] as const,
}
