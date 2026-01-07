import { useQuery } from '@tanstack/react-query'
import apiClient from '@/api/client'
import type { User } from '@/types/user'

interface UseAuthReturn {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
  error: Error | null
}

export function useAuth(): UseAuthReturn {
  const { data: user, isLoading, error } = useQuery<User>({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      const response = await apiClient.get('/auth/me')
      const userData = response.data.data
      // Normalize snake_case to camelCase for staffProfile
      if (userData.staff_profile && !userData.staffProfile) {
        userData.staffProfile = userData.staff_profile
      }
      return userData
    },
    retry: false,
    // Only fetch if we have an auth token
    enabled: !!localStorage.getItem('auth_token') || document.cookie.includes('XSRF-TOKEN'),
  })

  return {
    user: user ?? null,
    isAuthenticated: !!user,
    isLoading,
    error: error as Error | null,
  }
}
