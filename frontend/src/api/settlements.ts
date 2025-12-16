import apiClient from './client'
import type {
  Settlement,
  SettlementPreview,
  GenerateSettlementRequest,
  UpdateSettlementStatusRequest,
} from '@/types/pricing'

// Query keys factory
export const settlementKeys = {
  all: ['settlements'] as const,
  lists: () => [...settlementKeys.all, 'list'] as const,
  list: (filters?: Record<string, unknown>) => [...settlementKeys.lists(), filters] as const,
  details: () => [...settlementKeys.all, 'detail'] as const,
  detail: (id: number) => [...settlementKeys.details(), id] as const,
  preview: (trainerId: number, from: string, to: string) => [
    ...settlementKeys.all,
    'preview',
    trainerId,
    from,
    to,
  ] as const,
}

// Settlements API
export const settlementsApi = {
  preview: async (trainerId: number, from: string, to: string) => {
    const { data } = await apiClient.get<{ data: SettlementPreview }>('/admin/settlements/preview', {
      params: { trainer_id: trainerId, from, to },
    })
    return data.data
  },

  generate: async (settlementData: GenerateSettlementRequest) => {
    const { data } = await apiClient.post<{ data: Settlement }>('/admin/settlements/generate', settlementData)
    return data.data
  },

  list: async (params?: { trainer_id?: number; status?: string; from?: string; to?: string }) => {
    const { data } = await apiClient.get<{ data: Settlement[] }>('/admin/settlements', { params })
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: Settlement }>(`/admin/settlements/${id}`)
    return data.data
  },

  updateStatus: async (id: number, statusData: UpdateSettlementStatusRequest) => {
    const { data } = await apiClient.patch<{ data: Settlement }>(`/admin/settlements/${id}`, statusData)
    return data.data
  },

  delete: async (id: number) => {
    await apiClient.delete(`/admin/settlements/${id}`)
  },
}
