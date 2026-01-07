import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Bell, Calendar, Loader2, User, Lock } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { useAuth } from '@/hooks/useAuth';
import {
  notificationPreferencesApi,
  notificationPreferencesKeys,
  type NotificationPreferences,
  type UpdateNotificationPreferencesRequest,
} from '@/api/notificationPreferences';
import {
  profileApi,
  profileKeys,
  type UpdateProfileRequest,
  type ChangePasswordRequest,
} from '@/api/profile';

export default function SettingsPage() {
  const { t } = useTranslation(['settings', 'common']);
  const { toast } = useToast();
  const { user } = useAuth();
  const queryClient = useQueryClient();

  // Fetch profile
  const { data: profile, isLoading: profileLoading } = useQuery({
    queryKey: profileKeys.current(),
    queryFn: () => profileApi.get(),
  });

  // Fetch notification preferences
  const { data: preferences, isLoading: preferencesLoading } = useQuery({
    queryKey: notificationPreferencesKeys.current(),
    queryFn: () => notificationPreferencesApi.get(),
  });

  // Local state for profile form
  const [profileData, setProfileData] = useState<UpdateProfileRequest>({});
  const [passwordData, setPasswordData] = useState<ChangePasswordRequest>({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  // Local state for notification form
  const [notificationData, setNotificationData] = useState<NotificationPreferences>({
    email_reminder_24h: true,
    email_reminder_2h: false,
    gcal_sync_enabled: false,
    gcal_calendar_id: null,
  });

  // Sync profile data
  useEffect(() => {
    if (profile) {
      setProfileData({
        name: profile.name,
        email: profile.email,
        phone: profile.phone,
        date_of_birth: profile.date_of_birth,
        emergency_contact_name: profile.emergency_contact_name,
        emergency_contact_phone: profile.emergency_contact_phone,
        bio: profile.bio,
        specialization: profile.specialization,
      });
    }
  }, [profile]);

  // Sync notification preferences
  useEffect(() => {
    if (preferences) {
      setNotificationData(preferences);
    }
  }, [preferences]);

  // Profile update mutation
  const profileMutation = useMutation({
    mutationFn: (data: UpdateProfileRequest) => profileApi.update(data),
    onSuccess: (data) => {
      queryClient.setQueryData(profileKeys.current(), data);
      toast({
        title: t('settings:profile.updateSuccess', 'Profil frissítve'),
        description: t('settings:profile.updateSuccessDescription', 'Az adataid sikeresen mentve.'),
      });
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: t('settings:profile.updateError', 'Hiba a profil mentésekor'),
        variant: 'destructive',
      });
    },
  });

  // Password change mutation
  const passwordMutation = useMutation({
    mutationFn: (data: ChangePasswordRequest) => profileApi.changePassword(data),
    onSuccess: () => {
      setPasswordData({
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
      });
      toast({
        title: t('settings:password.updateSuccess', 'Jelszó megváltoztatva'),
        description: t('settings:password.updateSuccessDescription', 'Az új jelszavad aktív.'),
      });
    },
    onError: () => {
      toast({
        title: t('common:error'),
        description: t('settings:password.updateError', 'Hiba a jelszó módosításakor. Ellenőrizd a jelenlegi jelszót.'),
        variant: 'destructive',
      });
    },
  });

  // Notification preferences update mutation
  const notificationMutation = useMutation({
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

  const handleProfileSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    profileMutation.mutate(profileData);
  };

  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (passwordData.new_password !== passwordData.new_password_confirmation) {
      toast({
        title: t('common:error'),
        description: t('settings:password.mismatch', 'A jelszavak nem egyeznek'),
        variant: 'destructive',
      });
      return;
    }
    passwordMutation.mutate(passwordData);
  };

  const handleNotificationSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    notificationMutation.mutate(notificationData);
  };

  if (profileLoading || preferencesLoading) {
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

        {/* Profile Section */}
        <form onSubmit={handleProfileSubmit} className="space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <User className="w-5 h-5" />
                <CardTitle>{t('settings:profile.title', 'Személyes adatok')}</CardTitle>
              </div>
              <CardDescription>{t('settings:profile.description', 'Módosítsd a személyes adataidat')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name">{t('settings:profile.name', 'Név')}</Label>
                  <Input
                    id="name"
                    value={profileData.name || ''}
                    onChange={(e) => setProfileData((prev) => ({ ...prev, name: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="email">{t('settings:profile.email', 'Email cím')}</Label>
                  <Input
                    id="email"
                    type="email"
                    value={profileData.email || ''}
                    onChange={(e) => setProfileData((prev) => ({ ...prev, email: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="phone">{t('settings:profile.phone', 'Telefonszám')}</Label>
                  <Input
                    id="phone"
                    value={profileData.phone || ''}
                    onChange={(e) => setProfileData((prev) => ({ ...prev, phone: e.target.value }))}
                  />
                </div>
              </div>

              {/* Client-specific fields */}
              {user?.role === 'client' && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t">
                  <div className="space-y-2">
                    <Label htmlFor="date_of_birth">{t('settings:profile.dateOfBirth', 'Születési dátum')}</Label>
                    <Input
                      id="date_of_birth"
                      type="date"
                      value={profileData.date_of_birth || ''}
                      onChange={(e) => setProfileData((prev) => ({ ...prev, date_of_birth: e.target.value || null }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="emergency_contact_name">{t('settings:profile.emergencyContactName', 'Vészhelyzeti kapcsolat neve')}</Label>
                    <Input
                      id="emergency_contact_name"
                      value={profileData.emergency_contact_name || ''}
                      onChange={(e) => setProfileData((prev) => ({ ...prev, emergency_contact_name: e.target.value || null }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="emergency_contact_phone">{t('settings:profile.emergencyContactPhone', 'Vészhelyzeti kapcsolat telefonszáma')}</Label>
                    <Input
                      id="emergency_contact_phone"
                      value={profileData.emergency_contact_phone || ''}
                      onChange={(e) => setProfileData((prev) => ({ ...prev, emergency_contact_phone: e.target.value || null }))}
                    />
                  </div>
                </div>
              )}

              {/* Staff-specific fields */}
              {user?.role === 'staff' && (
                <div className="space-y-4 pt-4 border-t">
                  <div className="space-y-2">
                    <Label htmlFor="specialization">{t('settings:profile.specialization', 'Szakterület')}</Label>
                    <Input
                      id="specialization"
                      value={profileData.specialization || ''}
                      onChange={(e) => setProfileData((prev) => ({ ...prev, specialization: e.target.value || null }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="bio">{t('settings:profile.bio', 'Bemutatkozás')}</Label>
                    <Textarea
                      id="bio"
                      rows={3}
                      value={profileData.bio || ''}
                      onChange={(e) => setProfileData((prev) => ({ ...prev, bio: e.target.value || null }))}
                    />
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button type="submit" disabled={profileMutation.isPending} className="min-w-[120px]">
              {profileMutation.isPending ? (
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

        {/* Password Change Section */}
        <form onSubmit={handlePasswordSubmit} className="space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Lock className="w-5 h-5" />
                <CardTitle>{t('settings:password.title', 'Jelszó módosítása')}</CardTitle>
              </div>
              <CardDescription>{t('settings:password.description', 'Változtasd meg a jelszavadat')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="current_password">{t('settings:password.current', 'Jelenlegi jelszó')}</Label>
                  <Input
                    id="current_password"
                    type="password"
                    value={passwordData.current_password}
                    onChange={(e) => setPasswordData((prev) => ({ ...prev, current_password: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="new_password">{t('settings:password.new', 'Új jelszó')}</Label>
                  <Input
                    id="new_password"
                    type="password"
                    value={passwordData.new_password}
                    onChange={(e) => setPasswordData((prev) => ({ ...prev, new_password: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="new_password_confirmation">{t('settings:password.confirm', 'Új jelszó megerősítése')}</Label>
                  <Input
                    id="new_password_confirmation"
                    type="password"
                    value={passwordData.new_password_confirmation}
                    onChange={(e) => setPasswordData((prev) => ({ ...prev, new_password_confirmation: e.target.value }))}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button type="submit" disabled={passwordMutation.isPending} className="min-w-[120px]">
              {passwordMutation.isPending ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  {t('common:saving')}
                </>
              ) : (
                t('settings:password.change', 'Jelszó módosítása')
              )}
            </Button>
          </div>
        </form>

        {/* Email Notifications Section */}
        <form onSubmit={handleNotificationSubmit} className="space-y-6">
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
                  checked={notificationData.email_reminder_24h}
                  onCheckedChange={(checked) =>
                    setNotificationData((prev) => ({ ...prev, email_reminder_24h: checked }))
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
                  checked={notificationData.email_reminder_2h}
                  onCheckedChange={(checked) =>
                    setNotificationData((prev) => ({ ...prev, email_reminder_2h: checked }))
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
                  checked={notificationData.gcal_sync_enabled}
                  onCheckedChange={(checked) =>
                    setNotificationData((prev) => ({ ...prev, gcal_sync_enabled: checked }))
                  }
                />
              </div>

              {notificationData.gcal_sync_enabled && (
                <div className="space-y-2">
                  <Label htmlFor="gcal_calendar_id">
                    {t('settings:gcal.calendarId')}
                  </Label>
                  <Input
                    id="gcal_calendar_id"
                    type="text"
                    placeholder="your-email@gmail.com"
                    value={notificationData.gcal_calendar_id ?? ''}
                    onChange={(e) =>
                      setNotificationData((prev) => ({
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
              disabled={notificationMutation.isPending}
              className="min-w-[120px]"
            >
              {notificationMutation.isPending ? (
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
