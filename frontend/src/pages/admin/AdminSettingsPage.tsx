import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Settings, Clock, Send, Bell, Mail, Bug, Building2 } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useToast } from '@/hooks/use-toast'
import { adminSettingsApi, adminKeys, type UpdateNotificationSettingsRequest } from '@/api/admin'
import { Link } from 'react-router-dom'

export default function AdminSettingsPage() {
  const { t } = useTranslation(['admin', 'common'])
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [selectedHour, setSelectedHour] = useState<number | null>(null)
  const [debugEmailEnabled, setDebugEmailEnabled] = useState<boolean>(false)
  const [debugEmailAddress, setDebugEmailAddress] = useState<string>('')
  const [emailCompanyName, setEmailCompanyName] = useState<string>('')
  const [emailSupportEmail, setEmailSupportEmail] = useState<string>('')

  // Fetch notification settings
  const { data: settings, isLoading } = useQuery({
    queryKey: adminKeys.notificationSettings(),
    queryFn: () => adminSettingsApi.getNotificationSettings(),
  })

  // Sync state with fetched settings
  useEffect(() => {
    if (settings) {
      setDebugEmailEnabled(settings.debug_email_enabled)
      setDebugEmailAddress(settings.debug_email_address || '')
      setEmailCompanyName(settings.email_company_name || '')
      setEmailSupportEmail(settings.email_support_email || '')
    }
  }, [settings])

  // Update notification settings mutation
  const updateMutation = useMutation({
    mutationFn: (updates: UpdateNotificationSettingsRequest) => adminSettingsApi.updateNotificationSettings(updates),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: adminKeys.notificationSettings() })
      toast({
        title: t('settings.updateSuccess'),
        description: t('settings.updateSuccessDescription'),
      })
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('settings.updateError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  // Send daily schedules mutation
  const sendDailyMutation = useMutation({
    mutationFn: () => adminSettingsApi.sendDailySchedules(),
    onSuccess: (result) => {
      toast({
        title: t('settings.sendDailySuccess'),
        description: result.output || t('settings.sendDailySuccessDescription'),
      })
    },
    onError: (error: any) => {
      toast({
        variant: 'destructive',
        title: t('settings.sendDailyError'),
        description: error.response?.data?.message || t('common:error'),
      })
    },
  })

  const handleSaveHour = () => {
    if (selectedHour !== null) {
      updateMutation.mutate({ daily_schedule_notification_hour: selectedHour })
    }
  }

  const handleSaveDebugEmail = () => {
    updateMutation.mutate({
      debug_email_enabled: debugEmailEnabled,
      debug_email_address: debugEmailAddress || null,
    })
  }

  const handleSaveEmailTemplateVariables = () => {
    updateMutation.mutate({
      email_company_name: emailCompanyName,
      email_support_email: emailSupportEmail,
    })
  }

  const handleSendDaily = () => {
    sendDailyMutation.mutate()
  }

  // Generate hour options (0-23)
  const hourOptions = Array.from({ length: 24 }, (_, i) => ({
    value: i,
    label: `${i.toString().padStart(2, '0')}:00`,
  }))

  const currentHour = selectedHour ?? settings?.daily_schedule_notification_hour ?? 7

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('settings.title')}</h1>
          <p className="text-gray-500 mt-2">{t('settings.subtitle')}</p>
        </div>
      </div>

      {/* Notification Settings Card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Bell className="h-5 w-5" />
            {t('settings.notificationSettings')}
          </CardTitle>
          <CardDescription>{t('settings.notificationSettingsDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {isLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-10 w-full max-w-xs" />
              <Skeleton className="h-10 w-32" />
            </div>
          ) : (
            <>
              {/* Daily Schedule Notification Hour */}
              <div className="space-y-4">
                <div className="flex items-start gap-4">
                  <Clock className="h-5 w-5 text-muted-foreground mt-1" />
                  <div className="flex-1 space-y-2">
                    <Label htmlFor="notification-hour">{t('settings.dailyScheduleHour')}</Label>
                    <p className="text-sm text-muted-foreground">{t('settings.dailyScheduleHourDescription')}</p>
                    <div className="flex items-center gap-4">
                      <Select
                        value={currentHour.toString()}
                        onValueChange={(value) => setSelectedHour(parseInt(value, 10))}
                      >
                        <SelectTrigger className="w-32" id="notification-hour">
                          <SelectValue placeholder={t('settings.selectHour')} />
                        </SelectTrigger>
                        <SelectContent>
                          {hourOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value.toString()}>
                              {option.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <Button
                        onClick={handleSaveHour}
                        disabled={updateMutation.isPending || selectedHour === null || selectedHour === settings?.daily_schedule_notification_hour}
                      >
                        {updateMutation.isPending ? t('common:loading') : t('common:save')}
                      </Button>
                    </div>
                  </div>
                </div>
              </div>

              {/* Manual Send Button */}
              <div className="border-t pt-6">
                <div className="flex items-start gap-4">
                  <Send className="h-5 w-5 text-muted-foreground mt-1" />
                  <div className="flex-1 space-y-2">
                    <Label>{t('settings.sendDailyManually')}</Label>
                    <p className="text-sm text-muted-foreground">{t('settings.sendDailyManuallyDescription')}</p>
                    <Button
                      variant="secondary"
                      onClick={handleSendDaily}
                      disabled={sendDailyMutation.isPending}
                    >
                      <Send className="h-4 w-4 mr-2" />
                      {sendDailyMutation.isPending ? t('common:loading') : t('settings.sendNow')}
                    </Button>
                  </div>
                </div>
              </div>

              {/* Debug Email Settings */}
              <div className="border-t pt-6">
                <div className="flex items-start gap-4">
                  <Bug className="h-5 w-5 text-muted-foreground mt-1" />
                  <div className="flex-1 space-y-4">
                    <div>
                      <Label>{t('settings.debugEmail')}</Label>
                      <p className="text-sm text-muted-foreground">{t('settings.debugEmailDescription')}</p>
                    </div>

                    <div className="flex items-center space-x-2">
                      <Switch
                        id="debug-email-enabled"
                        checked={debugEmailEnabled}
                        onCheckedChange={setDebugEmailEnabled}
                      />
                      <Label htmlFor="debug-email-enabled">{t('settings.debugEmailEnabled')}</Label>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="debug-email-address">{t('settings.debugEmailAddress')}</Label>
                      <Input
                        id="debug-email-address"
                        type="email"
                        placeholder="debug@example.com"
                        value={debugEmailAddress}
                        onChange={(e) => setDebugEmailAddress(e.target.value)}
                        disabled={!debugEmailEnabled}
                        className="max-w-sm"
                      />
                    </div>

                    <Button
                      onClick={handleSaveDebugEmail}
                      disabled={updateMutation.isPending || (
                        debugEmailEnabled === settings?.debug_email_enabled &&
                        debugEmailAddress === (settings?.debug_email_address || '')
                      )}
                    >
                      {updateMutation.isPending ? t('common:loading') : t('common:save')}
                    </Button>
                  </div>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {/* Email Template Variables Card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Building2 className="h-5 w-5" />
            {t('settings.emailTemplateVariables')}
          </CardTitle>
          <CardDescription>{t('settings.emailTemplateVariablesDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {isLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-10 w-full max-w-md" />
              <Skeleton className="h-10 w-full max-w-md" />
            </div>
          ) : (
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="email-company-name">{t('settings.companyName')}</Label>
                <p className="text-sm text-muted-foreground">{t('settings.companyNameDescription')}</p>
                <Input
                  id="email-company-name"
                  placeholder="FunctionalFit Egeszsegkozpont"
                  value={emailCompanyName}
                  onChange={(e) => setEmailCompanyName(e.target.value)}
                  className="max-w-md"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="email-support-email">{t('settings.supportEmail')}</Label>
                <p className="text-sm text-muted-foreground">{t('settings.supportEmailDescription')}</p>
                <Input
                  id="email-support-email"
                  type="email"
                  placeholder="support@functionalfit.hu"
                  value={emailSupportEmail}
                  onChange={(e) => setEmailSupportEmail(e.target.value)}
                  className="max-w-md"
                />
              </div>

              <Button
                onClick={handleSaveEmailTemplateVariables}
                disabled={updateMutation.isPending || (
                  emailCompanyName === (settings?.email_company_name || '') &&
                  emailSupportEmail === (settings?.email_support_email || '')
                )}
              >
                {updateMutation.isPending ? t('common:loading') : t('common:save')}
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Email Templates Link Card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Mail className="h-5 w-5" />
            {t('settings.emailTemplates')}
          </CardTitle>
          <CardDescription>{t('settings.emailTemplatesDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <Link to="/admin/email-templates">
            <Button variant="outline">
              <Settings className="h-4 w-4 mr-2" />
              {t('settings.manageEmailTemplates')}
            </Button>
          </Link>
        </CardContent>
      </Card>
    </div>
  )
}
