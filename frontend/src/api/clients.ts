// Client API client functions
import apiClient from './client'
import type { ApiResponse } from '@/types/api'
import type {
  ClientActivityResponse,
  ActivityHistoryFilters,
  ClientPassesResponse,
  UpcomingBooking,
  ClientSearchResult,
} from '@/types/client'

export const clientsApi = {
  /**
   * Search clients by name or email (staff/admin only)
   */
  search: async (query: string): Promise<ClientSearchResult[]> => {
    const response = await apiClient.get<ApiResponse<ClientSearchResult[]>>(
      '/staff/clients/search',
      { params: { q: query } }
    )
    return response.data.data
  },

  /**
   * Get clients by IDs (batch fetch)
   */
  batch: async (ids: number[]): Promise<ClientSearchResult[]> => {
    if (ids.length === 0) return []
    const response = await apiClient.get<ApiResponse<ClientSearchResult[]>>(
      '/staff/clients/batch',
      { params: { ids: ids.join(',') } }
    )
    return response.data.data
  },

  /**
   * Get activity history for a client
   */
  getActivity: async (
    clientId: string,
    filters?: ActivityHistoryFilters
  ): Promise<ClientActivityResponse> => {
    const response = await apiClient.get<ApiResponse<ClientActivityResponse>>(
      `/clients/${clientId}/activity`,
      { params: filters }
    )
    return response.data.data
  },

  /**
   * Get client passes (active and expired)
   */
  getPasses: async (clientId: string): Promise<ClientPassesResponse> => {
    const response = await apiClient.get<ApiResponse<ClientPassesResponse>>(
      `/clients/${clientId}/passes`
    )
    return response.data.data
  },

  /**
   * Get upcoming bookings for a client
   */
  getUpcoming: async (clientId: string): Promise<UpcomingBooking[]> => {
    const response = await apiClient.get<ApiResponse<UpcomingBooking[]>>(
      `/clients/${clientId}/upcoming`
    )
    return response.data.data
  },
}

// React Query keys factory for clients
export const clientKeys = {
  all: ['clients'] as const,
  details: () => [...clientKeys.all, 'detail'] as const,
  detail: (id: string) => [...clientKeys.details(), id] as const,
  activity: (id: string, filters?: ActivityHistoryFilters) =>
    [...clientKeys.detail(id), 'activity', filters] as const,
  passes: (id: string) => [...clientKeys.detail(id), 'passes'] as const,
  upcoming: (id: string) => [...clientKeys.detail(id), 'upcoming'] as const,
}
