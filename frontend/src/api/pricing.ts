import apiClient from './client'
import type {
  ClassPricingDefault,
  ClientClassPricing,
  CreateClassPricingDefaultRequest,
  CreateClientClassPricingRequest,
} from '@/types/pricing'

// Query keys factory
export const pricingKeys = {
  all: ['pricing'] as const,
  classDefaults: () => [...pricingKeys.all, 'classDefaults'] as const,
  classDefaultsList: (filters?: Record<string, unknown>) => [...pricingKeys.classDefaults(), 'list', filters] as const,
  classDefault: (id: number) => [...pricingKeys.classDefaults(), id] as const,
  clientPricing: () => [...pricingKeys.all, 'clientPricing'] as const,
  clientPricingList: (clientId: number, filters?: Record<string, unknown>) => [
    ...pricingKeys.clientPricing(),
    clientId,
    'list',
    filters,
  ] as const,
  clientPrice: (id: number) => [...pricingKeys.clientPricing(), id] as const,
}

// Class Pricing Defaults API
export const classPricingDefaultsApi = {
  list: async (params?: { class_template_id?: number; is_active?: boolean }) => {
    const { data } = await apiClient.get<{ data: ClassPricingDefault[] }>('/admin/pricing/class-defaults', { params })
    return data.data
  },

  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: ClassPricingDefault }>(`/admin/pricing/class-defaults/${id}`)
    return data.data
  },

  create: async (pricingData: CreateClassPricingDefaultRequest) => {
    const { data } = await apiClient.post<{ data: ClassPricingDefault }>('/admin/pricing/class-defaults', pricingData)
    return data.data
  },

  update: async (id: number, pricingData: Partial<CreateClassPricingDefaultRequest>) => {
    const { data } = await apiClient.patch<{ data: ClassPricingDefault }>(
      `/admin/pricing/class-defaults/${id}`,
      pricingData
    )
    return data.data
  },

  toggleActive: async (id: number) => {
    const { data } = await apiClient.patch<{ data: ClassPricingDefault }>(
      `/admin/pricing/class-defaults/${id}/toggle-active`
    )
    return data.data
  },

  delete: async (id: number) => {
    await apiClient.delete(`/admin/pricing/class-defaults/${id}`)
  },

  assign: async (classTemplateId: number, pricingId: number) => {
    const { data } = await apiClient.post<{ data: ClassPricingDefault }>('/admin/pricing/assign', {
      class_template_id: classTemplateId,
      pricing_id: pricingId,
    })
    return data.data
  },

  assignEvent: async (eventId: number, pricingId: number) => {
    const { data } = await apiClient.post<{ data: unknown }>('/admin/pricing/assign-event', {
      event_id: eventId,
      pricing_id: pricingId,
    })
    return data.data
  },
}

// Client-Specific Class Pricing API
export const clientClassPricingApi = {
  listByClient: async (clientId: number) => {
    const { data } = await apiClient.get<{ data: ClientClassPricing[] }>(`/admin/pricing/clients/${clientId}`)
    return data.data
  },

  create: async (pricingData: CreateClientClassPricingRequest) => {
    const { data } = await apiClient.post<{ data: ClientClassPricing }>('/admin/pricing/client-class', pricingData)
    return data.data
  },

  update: async (id: number, pricingData: Partial<CreateClientClassPricingRequest>) => {
    const { data } = await apiClient.patch<{ data: ClientClassPricing }>(
      `/admin/pricing/client-class/${id}`,
      pricingData
    )
    return data.data
  },

  delete: async (id: number) => {
    await apiClient.delete(`/admin/pricing/client-class/${id}`)
  },
}
