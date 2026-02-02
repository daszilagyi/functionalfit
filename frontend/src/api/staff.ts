// Staff API client functions
import apiClient from './client'
import type { ApiResponse } from '@/types/api'
import type {
  StaffDashboardStats,
  PayoutReport,
  AttendanceReport,
} from '@/types/staff'

// Types for staff activity
export interface StaffEvent {
  id: number
  type: string
  title?: string
  client?: {
    id: number
    user?: {
      id: number
      name: string
      email: string
    }
  }
  additional_clients?: Array<{
    id: number
    user?: {
      id: number
      name: string
    }
  }>
  room?: {
    id: number
    name: string
  }
  starts_at: string
  ends_at: string
  status: string
  notes?: string
}

export interface StaffMySummaryParams {
  from: string
  to: string
  groupBy?: 'day' | 'week' | 'month'
}

export interface StaffMySummary {
  total_events: number
  total_hours: number
  total_clients: number
  attendance_rate: number
  periods?: Array<{
    period: string
    events: number
    hours: number
    clients: number
  }>
}

export const staffApi = {
  /**
   * Get dashboard stats for current staff member
   */
  getDashboardStats: async (): Promise<StaffDashboardStats> => {
    const response = await apiClient.get<ApiResponse<StaffDashboardStats>>(
      '/staff/dashboard'
    )
    return response.data.data
  },

  /**
   * Export payout report
   */
  exportPayout: async (dateFrom: string, dateTo: string): Promise<PayoutReport> => {
    const response = await apiClient.get<ApiResponse<PayoutReport>>(
      '/staff/exports/payout',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Export attendance report
   */
  exportAttendance: async (dateFrom: string, dateTo: string): Promise<AttendanceReport> => {
    const response = await apiClient.get<ApiResponse<AttendanceReport>>(
      '/staff/exports/attendance',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Download payout report as XLSX
   */
  downloadPayoutXlsx: async (dateFrom: string, dateTo: string): Promise<Blob> => {
    const response = await apiClient.get('/staff/exports/payout', {
      params: { date_from: dateFrom, date_to: dateTo, format: 'xlsx' },
      responseType: 'blob',
    })
    return response.data
  },

  /**
   * Download attendance report as XLSX
   */
  downloadAttendanceXlsx: async (dateFrom: string, dateTo: string): Promise<Blob> => {
    const response = await apiClient.get('/staff/exports/attendance', {
      params: { date_from: dateFrom, date_to: dateTo, format: 'xlsx' },
      responseType: 'blob',
    })
    return response.data
  },

  /**
   * Get staff member's personal events (my-events)
   */
  getMyEvents: async (dateFrom?: string, dateTo?: string): Promise<StaffEvent[]> => {
    const response = await apiClient.get<ApiResponse<StaffEvent[]>>(
      '/staff/my-events',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Get staff member's summary report
   */
  getMySummary: async (params: StaffMySummaryParams): Promise<StaffMySummary> => {
    const response = await apiClient.get<ApiResponse<StaffMySummary>>(
      '/staff/reports/my-summary',
      {
        params: {
          from: params.from,
          to: params.to,
          groupBy: params.groupBy || 'week',
        },
      }
    )
    return response.data.data
  },
}

// React Query keys factory for staff
export const staffKeys = {
  all: ['staff'] as const,
  dashboard: () => [...staffKeys.all, 'dashboard'] as const,
  exports: () => [...staffKeys.all, 'exports'] as const,
  payout: (dateFrom: string, dateTo: string) =>
    [...staffKeys.exports(), 'payout', dateFrom, dateTo] as const,
  attendance: (dateFrom: string, dateTo: string) =>
    [...staffKeys.exports(), 'attendance', dateFrom, dateTo] as const,
  myEvents: (dateFrom?: string, dateTo?: string) =>
    [...staffKeys.all, 'my-events', dateFrom, dateTo] as const,
  mySummary: (params: StaffMySummaryParams) =>
    [...staffKeys.all, 'my-summary', params] as const,
}
