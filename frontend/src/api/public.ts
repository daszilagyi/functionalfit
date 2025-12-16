// Public API client functions for unauthenticated access
import axios from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import type { ApiResponse } from '@/types/api'
import type {
  PublicClassOccurrence,
  PublicClassFilters,
  QuickRegisterRequest,
  QuickRegisterResponse,
} from '@/types/public'

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api/v1'

// Create a separate axios instance for public endpoints (no auth required)
const publicClient = axios.create({
  baseURL: API_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

export const publicApi = {
  /**
   * Get public class occurrences (unauthenticated)
   */
  getClasses: async (filters?: PublicClassFilters): Promise<PublicClassOccurrence[]> => {
    const response = await publicClient.get<ApiResponse<PublicClassOccurrence[]>>(
      '/public/classes',
      { params: filters }
    )
    return response.data.data
  },

  /**
   * Quick registration for new users
   */
  registerQuick: async (data: QuickRegisterRequest): Promise<QuickRegisterResponse> => {
    const response = await publicClient.post<ApiResponse<QuickRegisterResponse>>(
      '/auth/register-quick',
      data
    )
    return response.data.data
  },
}

// React Query keys factory for public classes
export const publicKeys = {
  all: ['public'] as const,
  classes: () => [...publicKeys.all, 'classes'] as const,
  classList: (filters?: PublicClassFilters) => [...publicKeys.classes(), filters] as const,
}

// React Query hooks

/**
 * Hook to fetch public class occurrences
 */
export const usePublicClasses = (filters?: PublicClassFilters) => {
  return useQuery({
    queryKey: publicKeys.classList(filters),
    queryFn: () => publicApi.getClasses(filters),
    staleTime: 2 * 60 * 1000, // 2 minutes
  })
}

/**
 * Hook for quick registration mutation
 */
export const useQuickRegister = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: publicApi.registerQuick,
    onSuccess: (response) => {
      // Store the token in localStorage
      localStorage.setItem('auth_token', response.token)
      // Invalidate auth queries to trigger re-fetch
      queryClient.invalidateQueries({ queryKey: ['auth'] })
    },
  })
}
