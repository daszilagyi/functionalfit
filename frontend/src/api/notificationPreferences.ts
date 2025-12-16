import apiClient from './client';

export interface NotificationPreferences {
  email_reminder_24h: boolean;
  email_reminder_2h: boolean;
  gcal_sync_enabled: boolean;
  gcal_calendar_id: string | null;
}

export interface NotificationPreferencesResponse {
  data: NotificationPreferences;
}

export interface UpdateNotificationPreferencesRequest {
  email_reminder_24h?: boolean;
  email_reminder_2h?: boolean;
  gcal_sync_enabled?: boolean;
  gcal_calendar_id?: string | null;
}

export interface UpdateNotificationPreferencesResponse {
  message: string;
  data: NotificationPreferences;
}

export const notificationPreferencesApi = {
  /**
   * Get current user's notification preferences
   */
  async get(): Promise<NotificationPreferences> {
    const response = await apiClient.get<NotificationPreferencesResponse>(
      '/api/v1/notification-preferences'
    );
    return response.data.data;
  },

  /**
   * Update current user's notification preferences
   */
  async update(
    preferences: UpdateNotificationPreferencesRequest
  ): Promise<NotificationPreferences> {
    const response = await apiClient.put<UpdateNotificationPreferencesResponse>(
      '/api/v1/notification-preferences',
      preferences
    );
    return response.data.data;
  },
};

// React Query keys factory
export const notificationPreferencesKeys = {
  all: ['notificationPreferences'] as const,
  current: () => [...notificationPreferencesKeys.all, 'current'] as const,
};
