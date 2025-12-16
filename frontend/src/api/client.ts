import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api/v1'

// Create axios instance with default config
const apiClient: AxiosInstance = axios.create({
  baseURL: API_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // For Laravel Sanctum cookie-based auth
})

// Request interceptor - add auth token if available
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Token will be handled via cookies (Laravel Sanctum)
    // If using API tokens instead, retrieve from localStorage and add to headers
    let token = localStorage.getItem('auth_token')

    // DEV MODE: If no token exists and we're in development, create a mock one
    // DISABLED: Use real login instead
    // if (!token && import.meta.env.DEV) {
    //   // Use a test token that exists in our database
    //   token = '7|pBLgFg9kaoeu9QNcmzWTEvKOHcx9qafRY3JEVSYZ499bb3ed'
    //   localStorage.setItem('auth_token', token)
    // }

    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor - handle common errors
apiClient.interceptors.response.use(
  (response) => {
    return response
  },
  (error: AxiosError) => {
    // Handle 401 Unauthorized - redirect to login (but not on public pages)
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      // Don't redirect if we're on a public page or auth pages
      const publicPaths = ['/public/', '/login', '/forgot-password', '/reset-password']
      const isPublicPage = publicPaths.some(path => window.location.pathname.startsWith(path))
      if (!isPublicPage) {
        window.location.href = '/login'
      }
    }

    // Handle 403 Forbidden
    if (error.response?.status === 403) {
      console.error('Access forbidden:', error.response.data)
    }

    // Handle 409 Conflict (booking collision)
    if (error.response?.status === 409) {
      console.error('Resource conflict:', error.response.data)
    }

    // Handle 422 Validation errors
    if (error.response?.status === 422) {
      console.error('Validation error:', error.response.data)
    }

    // Handle 423 Locked (time window locked)
    if (error.response?.status === 423) {
      console.error('Resource locked:', error.response.data)
    }

    return Promise.reject(error)
  }
)

export default apiClient
