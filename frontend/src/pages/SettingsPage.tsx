import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Bell, Calendar, Loader2 } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useToast } from '@/hooks/use-toast';
import {
  notificationPreferencesApi,
  notificationPreferencesKeys,
  type NotificationPreferences,
  type UpdateNotificationPreferencesRequest,
} from '@/api/notificationPreferences';

export default function SettingsPage() {
  const { t } = useTranslation(['settings', 'common']);
  const { toast } = useToast();
  const queryClient = useQueryClient();

  // Fetch current preferences
  const { data: preferences, isLoading } = useQuery({
    queryKey: notificationPreferencesKeys.current(),
    queryFn: () => notificationPreferencesApi.get(),
  });

  // Local state for form
  const [formData, setFormData] = useState<NotificationPreferences>({
    email_reminder_24h: preferences?.email_reminder_24h ?? true,
    email_reminder_2h: preferences?.email_reminder_2h ?? false,
    gcal_sync_enabled: preferences?.gcal_sync_enabled ?? false,
    gcal_calendar_id: preferences?.gcal_calendar_id ?? null,
  });

  // Sync form data with fetched preferences
  useEffect(() => {
    if (preferences) {
      setFormData(preferences);
    }
  }, [preferences]);

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: (data: UpdateNotificationPreferencesRequest) =>
      notificationPreferencesApi.update(data),
    onSuccess: (data) => {
      queryClient.setQueryData(notificationPreferencesKeys.current(), data);
      toast({
        title: t('settings:notifications.updateSuccess'),
        description: t('settings:notifications.updateSuccessDescription'),
      });
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: t('settings:notifications.updateError'),
        variant: 'destructive',
      });
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateMutation.mutate(formData);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8 max-w-4xl">
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold">{t('settings:title')}</h1>
          <p className="text-muted-foreground mt-2">{t('settings:description')}</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Email Notifications Section */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Bell className="w-5 h-5" />
                <CardTitle>{t('settings:notifications.title')}</CardTitle>
              </div>
              <CardDescription>{t('settings:notifications.description')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="email_reminder_24h">
                    {t('settings:notifications.email24h')}
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    {t('settings:notifications.email24hDescription')}
                  </p>
                </div>
                <Switch
                  id="email_reminder_24h"
                  checked={formData.email_reminder_24h}
                  onCheckedChange={(checked) =>
                    setFormData((prev) => ({ ...prev, email_reminder_24h: checked }))
                  }
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="email_reminder_2h">
                    {t('settings:notifications.email2h')}
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    {t('settings:notifications.email2hDescription')}
                  </p>
                </div>
                <Switch
                  id="email_reminder_2h"
                  checked={formData.email_reminder_2h}
                  onCheckedChange={(checked) =>
                    setFormData((prev) => ({ ...prev, email_reminder_2h: checked }))
                  }
                />
              </div>
            </CardContent>
          </Card>

          {/* Google Calendar Sync Section */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Calendar className="w-5 h-5" />
                <CardTitle>{t('settings:gcal.title')}</CardTitle>
              </div>
              <CardDescription>{t('settings:gcal.description')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="gcal_sync_enabled">
                    {t('settings:gcal.syncEnabled')}
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    {t('settings:gcal.syncEnabledDescription')}
                  </p>
                </div>
                <Switch
                  id="gcal_sync_enabled"
                  checked={formData.gcal_sync_enabled}
                  onCheckedChange={(checked) =>
                    setFormData((prev) => ({ ...prev, gcal_sync_enabled: checked }))
                  }
                />
              </div>

              {formData.gcal_sync_enabled && (
                <div className="space-y-2">
                  <Label htmlFor="gcal_calendar_id">
                    {t('settings:gcal.calendarId')}
                  </Label>
                  <Input
                    id="gcal_calendar_id"
                    type="text"
                    placeholder="your-email@gmail.com"
                    value={formData.gcal_calendar_id ?? ''}
                    onChange={(e) =>
                      setFormData((prev) => ({
                        ...prev,
                        gcal_calendar_id: e.target.value || null,
                      }))
                    }
                  />
                  <p className="text-sm text-muted-foreground">
                    {t('settings:gcal.calendarIdHelp')}
                  </p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Save Button */}
          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={updateMutation.isPending}
              className="min-w-[120px]"
            >
              {updateMutation.isPending ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  {t('common:saving')}
                </>
              ) : (
                t('common:saveChanges')
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
