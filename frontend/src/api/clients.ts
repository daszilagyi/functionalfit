// Client API client functions
import apiClient from './client'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type {
  ClientActivityResponse,
  ActivityHistoryFilters,
  ClientPassesResponse,
  UpcomingBooking,
  ClientSearchResult,
} from '@/types/client'

export interface StaffClient {
  id: number
  user_id: number
  name: string
  email: string | null
  phone: string | null
  status: string
  date_of_birth?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  notes?: string | null
  created_at: string
}

export interface CreateClientRequest {
  name: string
  email: string
  phone?: string
}

export interface UpdateClientRequest {
  name?: string
  email?: string
  phone?: string | null
  status?: string
  date_of_birth?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  notes?: string | null
}

export const clientsApi = {
  /**
   * List all clients (staff/admin only)
   */
  list: async (params?: { search?: string }): Promise<PaginatedResponse<StaffClient>> => {
    const response = await apiClient.get<ApiResponse<PaginatedResponse<StaffClient>>>(
      '/staff/clients',
      { params }
    )
    return response.data.data
  },

  /**
   * Create a new client (staff/admin only)
   */
  create: async (data: CreateClientRequest): Promise<StaffClient> => {
    const response = await apiClient.post<ApiResponse<StaffClient>>(
      '/staff/clients',
      data
    )
    return response.data.data
  },

  /**
   * Get a single client (staff/admin only)
   */
  get: async (id: number): Promise<StaffClient> => {
    const response = await apiClient.get<ApiResponse<StaffClient>>(
      `/staff/clients/${id}`
    )
    return response.data.data
  },

  /**
   * Update a client (staff/admin only)
   */
  update: async (id: number, data: UpdateClientRequest): Promise<StaffClient> => {
    const response = await apiClient.patch<ApiResponse<StaffClient>>(
      `/staff/clients/${id}`,
      data
    )
    return response.data.data
  },

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

  /**
   * Import clients from CSV (admin only)
   */
  importCsv: async (file: File): Promise<ClientImportResult> => {
    const formData = new FormData()
    formData.append('file', file)
    const response = await apiClient.post<ApiResponse<ClientImportResult>>(
      '/admin/clients/import',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    )
    return response.data.data
  },
}

export interface ClientImportResult {
  summary: {
    total: number
    created: number
    updated: number
    errors: number
  }
  imported: Array<{
    created: boolean
    client_id: number
    name: string
    email: string
    phone: string
    service_type: string | null
    entry_fee: number
    trainer_fee: number
    price_code_created: boolean
  }>
  errors: Array<{
    row: number
    data: string[]
    error: string
  }>
}

// React Query keys factory for clients
export const clientKeys = {
  all: ['clients'] as const,
  lists: () => [...clientKeys.all, 'list'] as const,
  list: (params?: { search?: string }) => [...clientKeys.lists(), params] as const,
  details: () => [...clientKeys.all, 'detail'] as const,
  detail: (id: string) => [...clientKeys.details(), id] as const,
  activity: (id: string, filters?: ActivityHistoryFilters) =>
    [...clientKeys.detail(id), 'activity', filters] as const,
  passes: (id: string) => [...clientKeys.detail(id), 'passes'] as const,
  upcoming: (id: string) => [...clientKeys.detail(id), 'upcoming'] as const,
}
