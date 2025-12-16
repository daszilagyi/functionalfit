import apiClient from './client'
import type {
  UserWithProfile,
  CreateUserRequest,
  UpdateUserRequest,
  Site,
  CreateSiteRequest,
  UpdateSiteRequest,
  Room,
  CreateRoomRequest,
  UpdateRoomRequest,
  ClassTemplate,
  CreateClassTemplateRequest,
  UpdateClassTemplateRequest,
  AttendanceReport,
  PayoutReport,
  RevenueReport,
  UtilizationReport,
  ClientActivityReport,
  EmailTemplate,
  CreateEmailTemplateRequest,
  UpdateEmailTemplateRequest,
  PreviewEmailTemplateRequest,
  SendTestEmailRequest,
  EmailTemplateVersion,
  EmailTemplateVariable,
  EventChange,
  CalendarChangeLog,
  CalendarChangeLogDetail,
  CalendarChangeFilters,
} from '@/types/admin'
import type { PaginatedResponse } from '@/types/api'

// Query keys factory
export const adminKeys = {
  all: ['admin'] as const,
  users: () => [...adminKeys.all, 'users'] as const,
  usersList: (filters?: Record<string, unknown>) => [...adminKeys.users(), 'list', filters] as const,
  user: (id: number) => [...adminKeys.users(), id] as const,
  sites: () => [...adminKeys.all, 'sites'] as const,
  sitesList: (filters?: Record<string, unknown>) => [...adminKeys.sites(), 'list', filters] as const,
  site: (id: number) => [...adminKeys.sites(), id] as const,
  rooms: () => [...adminKeys.all, 'rooms'] as const,
  roomsList: (filters?: Record<string, unknown>) => [...adminKeys.rooms(), 'list', filters] as const,
  room: (id: number) => [...adminKeys.rooms(), id] as const,
  classTemplates: () => [...adminKeys.all, 'classTemplates'] as const,
  classTemplatesList: (filters?: Record<string, unknown>) => [...adminKeys.classTemplates(), 'list', filters] as const,
  classTemplate: (id: number) => [...adminKeys.classTemplates(), id] as const,
  reports: () => [...adminKeys.all, 'reports'] as const,
  attendanceReport: (dateFrom: string, dateTo: string) => [...adminKeys.reports(), 'attendance', dateFrom, dateTo] as const,
  payoutReport: (dateFrom: string, dateTo: string) => [...adminKeys.reports(), 'payouts', dateFrom, dateTo] as const,
  revenueReport: (dateFrom: string, dateTo: string) => [...adminKeys.reports(), 'revenue', dateFrom, dateTo] as const,
  utilizationReport: (dateFrom: string, dateTo: string) => [...adminKeys.reports(), 'utilization', dateFrom, dateTo] as const,
  clientActivityReport: (dateFrom: string, dateTo: string) => [...adminKeys.reports(), 'clients', dateFrom, dateTo] as const,
  emailTemplates: () => [...adminKeys.all, 'emailTemplates'] as const,
  emailTemplatesList: (filters?: Record<string, unknown>) => [...adminKeys.emailTemplates(), 'list', filters] as const,
  emailTemplate: (id: number) => [...adminKeys.emailTemplates(), id] as const,
  emailTemplateVersions: (id: number) => [...adminKeys.emailTemplates(), id, 'versions'] as const,
  emailTemplateVariables: () => [...adminKeys.emailTemplates(), 'variables'] as const,
  eventChanges: () => [...adminKeys.all, 'eventChanges'] as const,
  eventChangesList: (filters?: Record<string, unknown>) => [...adminKeys.eventChanges(), 'list', filters] as const,
  calendarChanges: () => [...adminKeys.all, 'calendarChanges'] as const,
  calendarChangesList: (filters?: CalendarChangeFilters) => [...adminKeys.calendarChanges(), 'list', filters] as const,
  calendarChangeDetail: (id: number) => [...adminKeys.calendarChanges(), id] as const,
}

// User Management API
export const usersApi = {
  list: async (params?: { role?: string; status?: string; search?: string; has_unpaid_balance?: boolean }) => {
    const { data } = await apiClient.get<{ data: PaginatedResponse<UserWithProfile> }>('/admin/users', { params })
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: UserWithProfile }>(`/admin/users/${id}`)
    return data.data
  },

  create: async (userData: CreateUserRequest) => {
    const { data } = await apiClient.post<{ data: UserWithProfile }>('/admin/users', userData)
    return data.data
  },

  update: async (id: number, userData: UpdateUserRequest) => {
    const { data } = await apiClient.patch<{ data: UserWithProfile }>(`/admin/users/${id}`, userData)
    return data.data
  },

  delete: async (id: number) => {
    const { data } = await apiClient.delete<{ data: null }>(`/admin/users/${id}`)
    return data.data
  },
}

// Site Management API
export const sitesApi = {
  list: async (params?: { is_active?: boolean; search?: string }) => {
    const { data } = await apiClient.get<{ data: Site[] }>('/admin/sites', { params })
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: Site }>(`/admin/sites/${id}`)
    return data.data
  },

  create: async (siteData: CreateSiteRequest) => {
    const { data } = await apiClient.post<{ data: Site }>('/admin/sites', siteData)
    return data.data
  },

  update: async (id: number, siteData: UpdateSiteRequest) => {
    const { data } = await apiClient.patch<{ data: Site }>(`/admin/sites/${id}`, siteData)
    return data.data
  },

  delete: async (id: number) => {
    await apiClient.delete(`/admin/sites/${id}`)
  },

  toggleActive: async (id: number) => {
    const { data } = await apiClient.patch<{ data: Site }>(`/admin/sites/${id}/toggle-active`)
    return data.data
  },
}

// Room Management API
export const roomsApi = {
  list: async (params?: { site_id?: number }) => {
    const { data } = await apiClient.get<{ data: Room[] }>('/admin/rooms', { params })
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: Room }>(`/admin/rooms/${id}`)
    return data.data
  },

  create: async (roomData: CreateRoomRequest) => {
    const { data } = await apiClient.post<{ data: Room }>('/admin/rooms', roomData)
    return data.data
  },

  update: async (id: number, roomData: UpdateRoomRequest) => {
    const { data } = await apiClient.patch<{ data: Room }>(`/admin/rooms/${id}`, roomData)
    return data.data
  },

  delete: async (id: number) => {
    const { data } = await apiClient.delete<{ data: null }>(`/admin/rooms/${id}`)
    return data.data
  },
}

// Class Template Management API
export const classTemplatesApi = {
  list: async (params?: { is_active?: boolean }) => {
    const { data } = await apiClient.get<{ data: ClassTemplate[] }>('/admin/class-templates', { params })
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: ClassTemplate }>(`/admin/class-templates/${id}`)
    return data.data
  },

  create: async (templateData: CreateClassTemplateRequest) => {
    const { data } = await apiClient.post<{ data: ClassTemplate }>('/admin/class-templates', templateData)
    return data.data
  },

  update: async (id: number, templateData: UpdateClassTemplateRequest) => {
    const { data } = await apiClient.patch<{ data: ClassTemplate }>(`/admin/class-templates/${id}`, templateData)
    return data.data
  },

  delete: async (id: number) => {
    const { data } = await apiClient.delete<{ data: null }>(`/admin/class-templates/${id}`)
    return data.data
  },
}

// Reports API
export const reportsApi = {
  attendance: async (dateFrom: string, dateTo: string) => {
    const { data } = await apiClient.get<{ data: AttendanceReport }>('/admin/reports/attendance', {
      params: { date_from: dateFrom, date_to: dateTo },
    })
    return data.data
  },

  payouts: async (dateFrom: string, dateTo: string) => {
    const { data } = await apiClient.get<{ data: PayoutReport }>('/admin/reports/payouts', {
      params: { date_from: dateFrom, date_to: dateTo },
    })
    return data.data
  },

  revenue: async (dateFrom: string, dateTo: string) => {
    const { data } = await apiClient.get<{ data: RevenueReport }>('/admin/reports/revenue', {
      params: { date_from: dateFrom, date_to: dateTo },
    })
    return data.data
  },

  utilization: async (dateFrom: string, dateTo: string) => {
    const { data } = await apiClient.get<{ data: UtilizationReport }>('/admin/reports/utilization', {
      params: { date_from: dateFrom, date_to: dateTo },
    })
    return data.data
  },

  clientActivity: async (dateFrom: string, dateTo: string) => {
    const { data } = await apiClient.get<{ data: ClientActivityReport }>('/admin/reports/clients', {
      params: { date_from: dateFrom, date_to: dateTo },
    })
    return data.data
  },
}

// Email Template Management API
export const emailTemplatesApi = {
  list: async (params?: { is_active?: boolean; search?: string }) => {
    const { data } = await apiClient.get<{ data: { data: EmailTemplate[] } }>('/admin/email-templates', { params })
    return data.data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: { template: EmailTemplate; available_variables: EmailTemplateVariable[] } }>(`/admin/email-templates/${id}`)
    return data.data
  },

  create: async (templateData: CreateEmailTemplateRequest) => {
    const { data } = await apiClient.post<{ data: EmailTemplate }>('/admin/email-templates', templateData)
    return data.data
  },

  update: async (id: number, templateData: UpdateEmailTemplateRequest) => {
    const { data } = await apiClient.put<{ data: EmailTemplate }>(`/admin/email-templates/${id}`, templateData)
    return data.data
  },

  delete: async (id: number) => {
    const { data } = await apiClient.delete<{ data: null }>(`/admin/email-templates/${id}`)
    return data.data
  },

  preview: async (id: number, previewData: PreviewEmailTemplateRequest) => {
    const { data } = await apiClient.post<{ data: { preview: string; variables_used: Record<string, unknown> } }>(
      `/admin/email-templates/${id}/preview`,
      previewData
    )
    return data.data
  },

  sendTest: async (id: number, testData: SendTestEmailRequest) => {
    const { data } = await apiClient.post<{ data: { recipient: string } }>(
      `/admin/email-templates/${id}/send-test`,
      testData
    )
    return data.data
  },

  getVersions: async (id: number) => {
    const { data } = await apiClient.get<{ data: EmailTemplateVersion[] }>(`/admin/email-templates/${id}/versions`)
    return data.data
  },

  restore: async (id: number, versionId: number) => {
    const { data } = await apiClient.post<{ data: EmailTemplate }>(
      `/admin/email-templates/${id}/restore/${versionId}`
    )
    return data.data
  },

  getVariables: async () => {
    const { data } = await apiClient.get<{ data: { variables: EmailTemplateVariable[] } }>('/admin/email-templates-variables')
    return data.data.variables
  },
}

// Event Changes API (legacy)
export const eventChangesApi = {
  list: async (params?: { staff_id?: number; action?: string; date_from?: string; date_to?: string; per_page?: number }) => {
    const { data } = await apiClient.get<{ data: PaginatedResponse<EventChange> }>('/admin/event-changes', { params })
    return data.data
  },
}

// Calendar Changes API (new unified endpoint)
export const calendarChangesApi = {
  list: async (filters?: CalendarChangeFilters): Promise<{ data: CalendarChangeLog[]; meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null } }> => {
    const params = {
      actor_user_id: filters?.actorUserId,
      room_id: filters?.roomId,
      site: filters?.site,
      action: filters?.action,
      changed_from: filters?.changedFrom,
      changed_to: filters?.changedTo,
      sort: filters?.sort,
      order: filters?.order,
      page: filters?.page,
      per_page: filters?.perPage,
    }
    const { data } = await apiClient.get<{ data: CalendarChangeLog[]; meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null } }>('/admin/calendar-changes', { params })
    return data
  },

  getDetail: async (id: number) => {
    const { data } = await apiClient.get<CalendarChangeLogDetail>(`/admin/calendar-changes/${id}`)
    return data
  },
}
