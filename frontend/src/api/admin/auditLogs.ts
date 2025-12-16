// Admin API client for audit logs
import apiClient from '../client'
import type { ApiResponse } from '@/types/api'

export interface AuditLog {
  id: number
  event_id: number
  action: 'created' | 'updated' | 'deleted' | 'moved' | 'cancelled'
  by_user_id: number
  meta: {
    old?: Record<string, any>
    new?: Record<string, any>
  }
  created_at: string
  event?: {
    id: number
    type: string
    starts_at: string
    ends_at: string
    client?: {
      id: number
      user?: {
        name: string
        email: string
      }
    }
    room?: {
      id: number
      name: string
    }
  }
  user?: {
    id: number
    name: string
    email: string
    role: string
  }
}

export interface AuditLogFilters {
  event_id?: number
  action?: string
  date_from?: string
  date_to?: string
  user_id?: number
  per_page?: number
  page?: number
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const auditLogsApi = {
  /**
   * Get all audit logs (admin only)
   */
  list: async (filters?: AuditLogFilters): Promise<PaginatedResponse<AuditLog>> => {
    const response = await apiClient.get<ApiResponse<PaginatedResponse<AuditLog>>>(
      '/admin/audit-logs',
      { params: filters }
    )
    return response.data.data
  },

  /**
   * Get audit logs for a specific event (admin only)
   */
  getEventLogs: async (eventId: number): Promise<AuditLog[]> => {
    const response = await apiClient.get<ApiResponse<AuditLog[]>>(
      `/admin/events/${eventId}/audit-logs`
    )
    return response.data.data
  },
}

// React Query keys factory for audit logs
export const auditLogKeys = {
  all: ['admin', 'audit-logs'] as const,
  lists: () => [...auditLogKeys.all, 'list'] as const,
  list: (filters?: AuditLogFilters) => [...auditLogKeys.lists(), filters] as const,
  eventLogs: (eventId: number) => [...auditLogKeys.all, 'event', eventId] as const,
}
