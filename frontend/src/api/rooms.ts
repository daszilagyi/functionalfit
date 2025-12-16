// Room API client functions
import apiClient from './client'
import type { ApiResponse } from '@/types/api'

export interface Room {
  id: string
  name: string
  location: string
  facility: string
  capacity: number
  is_active: boolean
  created_at: string
  updated_at: string
}

export const roomsApi = {
  /**
   * Get all rooms (staff/admin only)
   */
  list: async (): Promise<Room[]> => {
    const response = await apiClient.get<ApiResponse<Room[]>>('/staff/rooms')
    return response.data.data
  },

  /**
   * Get a single room by ID
   */
  getById: async (roomId: string): Promise<Room> => {
    const response = await apiClient.get<ApiResponse<Room>>(`/staff/rooms/${roomId}`)
    return response.data.data
  },
}

// React Query keys factory for rooms
export const roomKeys = {
  all: ['rooms'] as const,
  lists: () => [...roomKeys.all, 'list'] as const,
  list: () => [...roomKeys.lists()] as const,
  details: () => [...roomKeys.all, 'detail'] as const,
  detail: (id: string) => [...roomKeys.details(), id] as const,
}
