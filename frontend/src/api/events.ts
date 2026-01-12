// Event API client functions (Staff)
import apiClient from './client'
import type { ApiResponse } from '@/types/api'
import type {
  Event,
  EventListFilters,
  CreateEventRequest,
  UpdateEventRequest,
  CheckInRequest,
  CheckInResponse,
} from '@/types/event'

export const eventsApi = {
  /**
   * Get my events (staff only)
   */
  getMyEvents: async (filters?: EventListFilters): Promise<Event[]> => {
    const response = await apiClient.get<ApiResponse<Event[]>>('/staff/my-events', {
      params: filters,
    })
    return response.data.data
  },

  /**
   * Get all events (admin only, with room filtering)
   */
  getAllEvents: async (filters?: EventListFilters): Promise<Event[]> => {
    const response = await apiClient.get<ApiResponse<Event[]>>('/admin/events', {
      params: filters,
    })
    return response.data.data
  },

  /**
   * Get all events for staff (staff can view all events but only edit their own)
   */
  getAllEventsForStaff: async (filters?: EventListFilters): Promise<Event[]> => {
    const response = await apiClient.get<ApiResponse<Event[]>>('/staff/all-events', {
      params: filters,
    })
    return response.data.data
  },

  /**
   * Create a new event (staff/admin only)
   */
  create: async (data: CreateEventRequest): Promise<Event> => {
    const response = await apiClient.post<ApiResponse<Event>>('/staff/events', data)
    return response.data.data
  },

  /**
   * Create a new event as admin (can assign to any staff)
   */
  adminCreate: async (data: CreateEventRequest): Promise<Event> => {
    const response = await apiClient.post<ApiResponse<Event>>('/admin/events', data)
    return response.data.data
  },

  /**
   * Update an event (same-day only for staff, admin can override)
   */
  update: async (eventId: string, data: UpdateEventRequest): Promise<Event> => {
    const response = await apiClient.patch<ApiResponse<Event>>(
      `/staff/events/${eventId}`,
      data
    )
    return response.data.data
  },

  /**
   * Update an event as admin (can update any event)
   */
  adminUpdate: async (eventId: string, data: UpdateEventRequest): Promise<Event> => {
    const response = await apiClient.put<ApiResponse<Event>>(
      `/admin/events/${eventId}`,
      data
    )
    return response.data.data
  },

  /**
   * Delete an event (staff endpoint - cannot delete past events)
   */
  delete: async (eventId: string): Promise<void> => {
    await apiClient.delete(`/staff/events/${eventId}`)
  },

  /**
   * Delete an event (admin endpoint - can delete any event including past)
   */
  adminDelete: async (eventId: string): Promise<void> => {
    await apiClient.delete(`/admin/events/${eventId}`)
  },

  /**
   * Check-in a client for an event
   */
  checkIn: async (eventId: string, data: CheckInRequest): Promise<CheckInResponse> => {
    const response = await apiClient.post<ApiResponse<CheckInResponse>>(
      `/staff/events/${eventId}/checkin`,
      data
    )
    return response.data.data
  },
}

// React Query keys factory for events
export const eventKeys = {
  all: ['events'] as const,
  lists: () => [...eventKeys.all, 'list'] as const,
  list: (filters?: EventListFilters) => [...eventKeys.lists(), filters] as const,
  myEvents: (filters?: EventListFilters) => [...eventKeys.all, 'my', filters] as const,
  allEvents: (filters?: EventListFilters) => [...eventKeys.all, 'admin-all', filters] as const,
  allEventsForStaff: (filters?: EventListFilters) => [...eventKeys.all, 'staff-all', filters] as const,
}
