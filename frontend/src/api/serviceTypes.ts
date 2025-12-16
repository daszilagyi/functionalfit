import apiClient from './client'
import type {
  ServiceType,
  ServiceTypeFormData,
  ClientPriceCode,
  ClientPriceCodeFormData,
  PricingResolveResponse,
} from '@/types/serviceType'

// Query keys factory
export const serviceTypeKeys = {
  all: ['serviceTypes'] as const,
  lists: () => [...serviceTypeKeys.all, 'list'] as const,
  list: (filters?: Record<string, unknown>) => [...serviceTypeKeys.lists(), filters] as const,
  detail: (id: number) => [...serviceTypeKeys.all, 'detail', id] as const,
}

export const clientPriceCodeKeys = {
  all: ['clientPriceCodes'] as const,
  lists: () => [...clientPriceCodeKeys.all, 'list'] as const,
  listByClient: (clientId: number) => [...clientPriceCodeKeys.lists(), 'client', clientId] as const,
  detail: (id: number) => [...clientPriceCodeKeys.all, 'detail', id] as const,
}

export const pricingResolveKeys = {
  all: ['pricingResolve'] as const,
  resolve: (email: string, serviceTypeCode: string) => [...pricingResolveKeys.all, email, serviceTypeCode] as const,
  resolveByIds: (clientId: number, serviceTypeId: number) =>
    [...pricingResolveKeys.all, 'byIds', clientId, serviceTypeId] as const,
}

// Service Types API
export const serviceTypesApi = {
  /**
   * List all service types
   */
  list: async (): Promise<ServiceType[]> => {
    const { data } = await apiClient.get<{ data: ServiceType[] }>('/admin/service-types')
    return data.data
  },

  /**
   * Get a single service type by ID
   */
  get: async (id: number): Promise<ServiceType> => {
    const { data } = await apiClient.get<{ data: ServiceType }>(`/admin/service-types/${id}`)
    return data.data
  },

  /**
   * Create a new service type
   */
  create: async (serviceTypeData: ServiceTypeFormData): Promise<{ service_type: ServiceType; price_codes_created: number }> => {
    const { data } = await apiClient.post<{ data: { service_type: ServiceType; price_codes_created: number } }>(
      '/admin/service-types',
      serviceTypeData
    )
    return data.data
  },

  /**
   * Update an existing service type
   */
  update: async (id: number, serviceTypeData: Partial<ServiceTypeFormData>): Promise<ServiceType> => {
    const { data } = await apiClient.patch<{ data: ServiceType }>(`/admin/service-types/${id}`, serviceTypeData)
    return data.data
  },

  /**
   * Toggle active status of a service type
   */
  toggleActive: async (id: number): Promise<ServiceType> => {
    const { data } = await apiClient.patch<{ data: ServiceType }>(`/admin/service-types/${id}/toggle-active`)
    return data.data
  },

  /**
   * Delete a service type
   */
  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/admin/service-types/${id}`)
  },
}

// Client Price Codes API
export const clientPriceCodesApi = {
  /**
   * List all price codes for a client
   */
  listByClient: async (clientId: number): Promise<ClientPriceCode[]> => {
    const { data } = await apiClient.get<{ data: ClientPriceCode[] }>(`/admin/clients/${clientId}/price-codes`)
    return data.data
  },

  /**
   * Create a new price code for a client
   */
  create: async (clientId: number, priceCodeData: ClientPriceCodeFormData): Promise<ClientPriceCode> => {
    const { data } = await apiClient.post<{ data: ClientPriceCode }>(
      `/admin/clients/${clientId}/price-codes`,
      priceCodeData
    )
    return data.data
  },

  /**
   * Update an existing price code
   */
  update: async (id: number, priceCodeData: Partial<ClientPriceCodeFormData>): Promise<ClientPriceCode> => {
    const { data } = await apiClient.patch<{ data: ClientPriceCode }>(`/admin/client-price-codes/${id}`, priceCodeData)
    return data.data
  },

  /**
   * Toggle active status of a price code
   */
  toggleActive: async (id: number): Promise<ClientPriceCode> => {
    const { data } = await apiClient.patch<{ data: ClientPriceCode }>(`/admin/client-price-codes/${id}/toggle-active`)
    return data.data
  },

  /**
   * Delete a price code
   */
  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/admin/client-price-codes/${id}`)
  },
}

// Pricing Resolve API
export const pricingResolveApi = {
  /**
   * Resolve pricing by client email and service type code
   */
  resolve: async (clientEmail: string, serviceTypeCode: string): Promise<PricingResolveResponse> => {
    const { data } = await apiClient.get<{ data: PricingResolveResponse }>('/pricing/resolve', {
      params: {
        client_email: clientEmail,
        service_type_code: serviceTypeCode,
      },
    })
    return data.data
  },

  /**
   * Resolve pricing by client ID and service type ID
   */
  resolveByIds: async (clientId: number, serviceTypeId: number): Promise<PricingResolveResponse> => {
    const { data } = await apiClient.get<{ data: PricingResolveResponse }>('/pricing/resolve-by-ids', {
      params: {
        client_id: clientId,
        service_type_id: serviceTypeId,
      },
    })
    return data.data
  },
}
