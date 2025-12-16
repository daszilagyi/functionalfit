import { QueryClient, type DefaultOptions } from '@tanstack/react-query'

const queryConfig: DefaultOptions = {
  queries: {
    refetchOnWindowFocus: false,
    retry: 1,
    staleTime: 5 * 60 * 1000, // 5 minutes
    gcTime: 10 * 60 * 1000, // 10 minutes (previously cacheTime)
  },
  mutations: {
    retry: 0,
  },
}

export const queryClient = new QueryClient({
  defaultOptions: queryConfig,
})
