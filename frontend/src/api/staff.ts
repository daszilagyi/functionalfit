// Staff API client functions
import apiClient from './client'
import type { ApiResponse } from '@/types/api'
import type {
  StaffDashboardStats,
  PayoutReport,
  AttendanceReport,
} from '@/types/staff'

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
}
