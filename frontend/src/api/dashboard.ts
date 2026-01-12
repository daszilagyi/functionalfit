import apiClient from './client'

export interface DashboardStats {
  today_events: number
  weekly_hours: number
  active_clients: number
  upcoming_events: number
  today_bookings: number
}

export const dashboardApi = {
  getStats: async (): Promise<DashboardStats> => {
    const response = await apiClient.get('/dashboard/stats')
    return response.data.data
  },
}
