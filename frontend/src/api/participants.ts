// Participant management API functions
import apiClient from './client'
import type { ApiResponse } from '@/types/api'

// Types for participant management
export interface ClassParticipant {
  registration_id: string
  client_id: string
  client_name: string
  client_email: string
  status: 'booked' | 'waitlist' | 'cancelled' | 'attended'
  payment_status: 'pending' | 'paid' | 'unpaid' | 'comped'
  booked_at: string
  checked_in_at: string | null
}

export interface ClassParticipantsResponse {
  occurrence_id: string
  participants: ClassParticipant[]
  total: number
  capacity: number
}

export interface EventParticipant {
  client_id: string
  client_name: string
  client_email: string
}

export interface EventParticipantResponse {
  event_id: string
  participant: EventParticipant | null
}

export interface AddClassParticipantRequest {
  client_id: number
  status?: 'booked' | 'waitlist'
  skip_payment?: boolean
  notes?: string
}

export interface AssignEventParticipantRequest {
  client_id: number
}

// Admin Participant API (can manage any event/class)
export const adminParticipantsApi = {
  // Class occurrence participants
  listClassParticipants: async (occurrenceId: string | number): Promise<ClassParticipantsResponse> => {
    const response = await apiClient.get<ApiResponse<ClassParticipantsResponse>>(
      `/admin/class-occurrences/${occurrenceId}/participants`
    )
    return response.data.data
  },

  addClassParticipant: async (occurrenceId: string | number, data: AddClassParticipantRequest): Promise<ClassParticipant> => {
    const response = await apiClient.post<ApiResponse<ClassParticipant>>(
      `/admin/class-occurrences/${occurrenceId}/participants`,
      data
    )
    return response.data.data
  },

  removeClassParticipant: async (
    occurrenceId: string | number,
    clientId: string | number,
    options?: { refund?: boolean; reason?: string }
  ): Promise<{ message: string }> => {
    const response = await apiClient.delete<ApiResponse<{ message: string }>>(
      `/admin/class-occurrences/${occurrenceId}/participants/${clientId}`,
      { data: options }
    )
    return response.data.data
  },

  // Event participants (1:1 sessions)
  getEventParticipant: async (eventId: string | number): Promise<EventParticipantResponse> => {
    const response = await apiClient.get<ApiResponse<EventParticipantResponse>>(
      `/admin/events/${eventId}/participant`
    )
    return response.data.data
  },

  assignEventParticipant: async (eventId: string | number, data: AssignEventParticipantRequest): Promise<unknown> => {
    const response = await apiClient.post<ApiResponse<unknown>>(
      `/admin/events/${eventId}/participant`,
      data
    )
    return response.data.data
  },

  removeEventParticipant: async (eventId: string | number): Promise<{ message: string }> => {
    const response = await apiClient.delete<ApiResponse<{ message: string }>>(
      `/admin/events/${eventId}/participant`
    )
    return response.data.data
  },
}

// Staff Participant API (can only manage their own events/classes)
export const staffParticipantsApi = {
  // Class occurrence participants
  listClassParticipants: async (occurrenceId: string | number): Promise<ClassParticipantsResponse> => {
    const response = await apiClient.get<ApiResponse<ClassParticipantsResponse>>(
      `/staff/class-occurrences/${occurrenceId}/participants`
    )
    return response.data.data
  },

  addClassParticipant: async (occurrenceId: string | number, data: AddClassParticipantRequest): Promise<ClassParticipant> => {
    const response = await apiClient.post<ApiResponse<ClassParticipant>>(
      `/staff/class-occurrences/${occurrenceId}/participants`,
      data
    )
    return response.data.data
  },

  removeClassParticipant: async (
    occurrenceId: string | number,
    clientId: string | number,
    options?: { refund?: boolean; reason?: string }
  ): Promise<{ message: string }> => {
    const response = await apiClient.delete<ApiResponse<{ message: string }>>(
      `/staff/class-occurrences/${occurrenceId}/participants/${clientId}`,
      { data: options }
    )
    return response.data.data
  },

  // Event participants (1:1 sessions)
  getEventParticipant: async (eventId: string | number): Promise<EventParticipantResponse> => {
    const response = await apiClient.get<ApiResponse<EventParticipantResponse>>(
      `/staff/events/${eventId}/participant`
    )
    return response.data.data
  },

  assignEventParticipant: async (eventId: string | number, data: AssignEventParticipantRequest): Promise<unknown> => {
    const response = await apiClient.post<ApiResponse<unknown>>(
      `/staff/events/${eventId}/participant`,
      data
    )
    return response.data.data
  },

  removeEventParticipant: async (eventId: string | number): Promise<{ message: string }> => {
    const response = await apiClient.delete<ApiResponse<{ message: string }>>(
      `/staff/events/${eventId}/participant`
    )
    return response.data.data
  },
}

// React Query keys factory for participants
export const participantKeys = {
  all: ['participants'] as const,
  classParticipants: (occurrenceId: string | number) => [...participantKeys.all, 'class', occurrenceId] as const,
  eventParticipant: (eventId: string | number) => [...participantKeys.all, 'event', eventId] as const,
}
