import apiClient from './client'
import type {
  GoogleCalendarSyncConfig,
  GoogleCalendarSyncLog,
  CreateSyncConfigInput,
  UpdateSyncConfigInput,
  ImportEventsInput,
  ExportEventsInput,
  ResolveConflictsInput,
  TestConnectionInput,
  TestConnectionResponse,
} from '@/types/googleCalendar'
import type { PaginatedResponse } from '@/types/api'

// Query keys factory
export const googleCalendarSyncKeys = {
  all: ['googleCalendarSync'] as const,
  configs: () => [...googleCalendarSyncKeys.all, 'configs'] as const,
  configsList: () => [...googleCalendarSyncKeys.configs(), 'list'] as const,
  config: (id: number) => [...googleCalendarSyncKeys.configs(), id] as const,
  logs: () => [...googleCalendarSyncKeys.all, 'logs'] as const,
  logsList: (filters?: Record<string, unknown>) => [...googleCalendarSyncKeys.logs(), 'list', filters] as const,
  log: (id: number) => [...googleCalendarSyncKeys.logs(), id] as const,
}

// Sync Config Management API
export const syncConfigsApi = {
  list: async () => {
    const { data } = await apiClient.get<{ success: boolean; data: GoogleCalendarSyncConfig[] }>(
      '/admin/google-calendar-sync/configs'
    )
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ success: boolean; data: GoogleCalendarSyncConfig }>(
      `/admin/google-calendar-sync/configs/${id}`
    )
    return data.data
  },

  create: async (configData: CreateSyncConfigInput) => {
    const { data } = await apiClient.post<{ success: boolean; data: GoogleCalendarSyncConfig; message: string }>(
      '/admin/google-calendar-sync/configs',
      configData
    )
    return data.data
  },

  update: async (id: number, configData: UpdateSyncConfigInput) => {
    const { data } = await apiClient.put<{ success: boolean; data: GoogleCalendarSyncConfig; message: string }>(
      `/admin/google-calendar-sync/configs/${id}`,
      configData
    )
    return data.data
  },

  delete: async (id: number) => {
    const { data } = await apiClient.delete<{ success: boolean; message: string }>(
      `/admin/google-calendar-sync/configs/${id}`
    )
    return data
  },
}

// Sync Operations API
export const syncOperationsApi = {
  testConnection: async (input: TestConnectionInput): Promise<TestConnectionResponse> => {
    const { data } = await apiClient.post<TestConnectionResponse>(
      '/admin/google-calendar-sync/test-connection',
      input
    )
    return data
  },

  import: async (input: ImportEventsInput) => {
    const { data } = await apiClient.post<{
      success: boolean
      data: GoogleCalendarSyncLog
      message: string
      conflicts?: any[]
    }>('/admin/google-calendar-sync/import', input)
    return data
  },

  export: async (input: ExportEventsInput) => {
    const { data } = await apiClient.post<{
      success: boolean
      data: GoogleCalendarSyncLog
      message: string
      results: {
        created: number
        updated: number
        skipped: number
        failed: number
        errors: any[]
      }
    }>('/admin/google-calendar-sync/export', input)
    return data
  },
}

// Sync Logs API
export const syncLogsApi = {
  list: async (params?: { sync_config_id?: number; operation?: string; status?: string; per_page?: number }) => {
    const { data } = await apiClient.get<{ success: boolean; data: PaginatedResponse<GoogleCalendarSyncLog> }>(
      '/admin/google-calendar-sync/logs',
      { params }
    )
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ success: boolean; data: GoogleCalendarSyncLog }>(
      `/admin/google-calendar-sync/logs/${id}`
    )
    return data.data
  },

  cancel: async (id: number) => {
    const { data } = await apiClient.post<{ success: boolean; message: string }>(
      `/admin/google-calendar-sync/logs/${id}/cancel`
    )
    return data
  },

  resolveConflicts: async (id: number, input: ResolveConflictsInput) => {
    const { data } = await apiClient.post<{ success: boolean; data: GoogleCalendarSyncLog; message: string }>(
      `/admin/google-calendar-sync/logs/${id}/resolve-conflicts`,
      input
    )
    return data.data
  },
}
