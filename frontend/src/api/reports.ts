// Reports API client functions
import apiClient from './client'
import type { ApiResponse } from '@/types/api'
import type {
  AttendanceReportData,
  PayoutReportData,
  RevenueReportData,
  UtilizationReportData,
  ClientActivityReportData,
  StaffDashboardStats,
  PayoutExportData,
  AttendanceExportData,
  ClientSummaryStats,
  ClientSessionHistory,
  ClientPassSummary,
  ExportFormat,
} from '@/types/reports'

// ============================================
// ADMIN REPORTS API
// ============================================

export const adminReportsApi = {
  /**
   * Get attendance report
   */
  getAttendance: async (dateFrom: string, dateTo: string): Promise<AttendanceReportData> => {
    const response = await apiClient.get<ApiResponse<AttendanceReportData>>(
      '/admin/reports/attendance',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Get payouts report
   */
  getPayouts: async (dateFrom: string, dateTo: string): Promise<PayoutReportData> => {
    const response = await apiClient.get<ApiResponse<PayoutReportData>>(
      '/admin/reports/payouts',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Get revenue report
   */
  getRevenue: async (dateFrom: string, dateTo: string): Promise<RevenueReportData> => {
    const response = await apiClient.get<ApiResponse<RevenueReportData>>(
      '/admin/reports/revenue',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Get utilization report
   */
  getUtilization: async (dateFrom: string, dateTo: string): Promise<UtilizationReportData> => {
    const response = await apiClient.get<ApiResponse<UtilizationReportData>>(
      '/admin/reports/utilization',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Get client activity report
   */
  getClientActivity: async (dateFrom: string, dateTo: string): Promise<ClientActivityReportData> => {
    const response = await apiClient.get<ApiResponse<ClientActivityReportData>>(
      '/admin/reports/clients',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Export report to Excel/CSV
   */
  exportReport: async (
    reportType: 'attendance' | 'payouts' | 'revenue' | 'utilization' | 'clients',
    dateFrom: string,
    dateTo: string,
    _format: ExportFormat = 'xlsx'
  ): Promise<Blob> => {
    const response = await apiClient.get(`/admin/reports/${reportType}/export`, {
      params: { date_from: dateFrom, date_to: dateTo },
      responseType: 'blob',
    })
    return response.data
  },
}

// ============================================
// STAFF REPORTS API
// ============================================

export const staffReportsApi = {
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
   * Export payout report (JSON response)
   */
  getPayoutReport: async (dateFrom: string, dateTo: string): Promise<PayoutExportData> => {
    const response = await apiClient.get<ApiResponse<PayoutExportData>>(
      '/staff/exports/payout',
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Export attendance report (JSON response)
   */
  getAttendanceReport: async (dateFrom: string, dateTo: string): Promise<AttendanceExportData> => {
    const response = await apiClient.get<ApiResponse<AttendanceExportData>>(
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

// ============================================
// CLIENT REPORTS API
// ============================================

export const clientReportsApi = {
  /**
   * Get client summary statistics
   */
  getSummaryStats: async (clientId: number, dateFrom?: string, dateTo?: string): Promise<ClientSummaryStats> => {
    const response = await apiClient.get<ApiResponse<ClientSummaryStats>>(
      `/clients/${clientId}/activity/summary`,
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Get client session history
   */
  getSessionHistory: async (
    clientId: number,
    dateFrom?: string,
    dateTo?: string
  ): Promise<ClientSessionHistory[]> => {
    const response = await apiClient.get<ApiResponse<ClientSessionHistory[]>>(
      `/clients/${clientId}/activity`,
      {
        params: { date_from: dateFrom, date_to: dateTo },
      }
    )
    return response.data.data
  },

  /**
   * Get client passes
   */
  getPasses: async (clientId: number): Promise<ClientPassSummary[]> => {
    const response = await apiClient.get<ApiResponse<ClientPassSummary[]>>(
      `/clients/${clientId}/passes`
    )
    return response.data.data
  },
}

// ============================================
// REACT QUERY KEYS FACTORY
// ============================================

export const reportKeys = {
  // Admin reports
  admin: {
    all: ['admin', 'reports'] as const,
    attendance: (dateFrom: string, dateTo: string) =>
      [...reportKeys.admin.all, 'attendance', dateFrom, dateTo] as const,
    payouts: (dateFrom: string, dateTo: string) =>
      [...reportKeys.admin.all, 'payouts', dateFrom, dateTo] as const,
    revenue: (dateFrom: string, dateTo: string) =>
      [...reportKeys.admin.all, 'revenue', dateFrom, dateTo] as const,
    utilization: (dateFrom: string, dateTo: string) =>
      [...reportKeys.admin.all, 'utilization', dateFrom, dateTo] as const,
    clientActivity: (dateFrom: string, dateTo: string) =>
      [...reportKeys.admin.all, 'client-activity', dateFrom, dateTo] as const,
  },

  // Staff reports
  staff: {
    all: ['staff', 'reports'] as const,
    dashboard: () => [...reportKeys.staff.all, 'dashboard'] as const,
    payout: (dateFrom: string, dateTo: string) =>
      [...reportKeys.staff.all, 'payout', dateFrom, dateTo] as const,
    attendance: (dateFrom: string, dateTo: string) =>
      [...reportKeys.staff.all, 'attendance', dateFrom, dateTo] as const,
  },

  // Client reports
  client: {
    all: (clientId: number) => ['client', clientId, 'reports'] as const,
    summary: (clientId: number, dateFrom?: string, dateTo?: string) =>
      [...reportKeys.client.all(clientId), 'summary', dateFrom, dateTo] as const,
    sessions: (clientId: number, dateFrom?: string, dateTo?: string) =>
      [...reportKeys.client.all(clientId), 'sessions', dateFrom, dateTo] as const,
    passes: (clientId: number) =>
      [...reportKeys.client.all(clientId), 'passes'] as const,
  },
}
