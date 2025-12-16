import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Loader2, CheckCircle, XCircle } from 'lucide-react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useToast } from '@/hooks/use-toast'
import { syncConfigsApi, syncOperationsApi, googleCalendarSyncKeys } from '@/api/googleCalendarSync'
import { roomsApi } from '@/api/admin'
import type { GoogleCalendarSyncConfig, CreateSyncConfigInput } from '@/types/googleCalendar'

const syncConfigSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  google_calendar_id: z.string().min(1, 'Calendar ID is required'),
  room_id: z.number().nullable(),
  sync_enabled: z.boolean(),
  sync_direction: z.enum(['import', 'export', 'both']),
})

type SyncConfigFormData = z.infer<typeof syncConfigSchema>

interface SyncConfigDialogProps {
  open: boolean
  onClose: () => void
  config: GoogleCalendarSyncConfig | null
}

export function SyncConfigDialog({ open, onClose, config }: SyncConfigDialogProps) {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const {
    control,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<SyncConfigFormData>({
    resolver: zodResolver(syncConfigSchema),
    defaultValues: {
      name: '',
      google_calendar_id: '',
      room_id: null,
      sync_enabled: true,
      sync_direction: 'both',
    },
  })

  const googleCalendarId = watch('google_calendar_id')

  // Fetch rooms
  const { data: rooms } = useQuery({
    queryKey: ['rooms'],
    queryFn: () => roomsApi.list(),
  })

  // Test connection mutation
  const testConnectionMutation = useMutation({
    mutationFn: syncOperationsApi.testConnection,
  })

  // Create/Update mutation
  const saveMutation = useMutation({
    mutationFn: (data: CreateSyncConfigInput) => {
      if (config) {
        return syncConfigsApi.update(config.id, data)
      }
      return syncConfigsApi.create(data)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: googleCalendarSyncKeys.configs() })
      toast({
        title: config ? t('googleCalendarSync.configUpdated') : t('googleCalendarSync.configCreated'),
        description: config
          ? t('googleCalendarSync.configUpdatedDescription')
          : t('googleCalendarSync.configCreatedDescription'),
      })
      onClose()
      reset()
    },
    onError: (error: any) => {
      toast({
        title: t('common.error'),
        description: error.response?.data?.message || t('googleCalendarSync.saveError'),
        variant: 'destructive',
      })
    },
  })

  useEffect(() => {
    if (config && open) {
      reset({
        name: config.name,
        google_calendar_id: config.google_calendar_id,
        room_id: config.room_id,
        sync_enabled: config.sync_enabled,
        sync_direction: config.sync_direction,
      })
    } else if (!open) {
      reset({
        name: '',
        google_calendar_id: '',
        room_id: null,
        sync_enabled: true,
        sync_direction: 'both',
      })
    }
  }, [config, open, reset])

  const onSubmit = (data: SyncConfigFormData) => {
    saveMutation.mutate(data)
  }

  const handleTestConnection = async () => {
    if (!googleCalendarId) {
      toast({
        title: t('common.error'),
        description: t('googleCalendarSync.enterCalendarId'),
        variant: 'destructive',
      })
      return
    }

    const result = await testConnectionMutation.mutateAsync({ google_calendar_id: googleCalendarId })

    if (result.success) {
      toast({
        title: t('googleCalendarSync.connectionSuccess'),
        description: result.data
          ? `${result.data.calendar_summary} (${result.data.calendar_timezone})`
          : t('googleCalendarSync.connectionSuccessDescription'),
      })
    } else {
      toast({
        title: t('googleCalendarSync.connectionFailed'),
        description: result.message,
        variant: 'destructive',
      })
    }
  }

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle>
            {config ? t('googleCalendarSync.editConfig') : t('googleCalendarSync.newConfig')}
          </DialogTitle>
          <DialogDescription>
            {config
              ? t('googleCalendarSync.editConfigDescription')
              : t('googleCalendarSync.newConfigDescription')}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="name">{t('googleCalendarSync.name')} *</Label>
            <Controller
              name="name"
              control={control}
              render={({ field }) => (
                <Input
                  {...field}
                  id="name"
                  placeholder={t('googleCalendarSync.namePlaceholder')}
                  className={errors.name ? 'border-destructive' : ''}
                />
              )}
            />
            {errors.name && <p className="text-sm text-destructive">{errors.name.message}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="google_calendar_id">{t('googleCalendarSync.calendarId')} *</Label>
            <div className="flex gap-2">
              <Controller
                name="google_calendar_id"
                control={control}
                render={({ field }) => (
                  <Input
                    {...field}
                    id="google_calendar_id"
                    placeholder="primary or calendar-id@group.calendar.google.com"
                    className={errors.google_calendar_id ? 'border-destructive' : ''}
                  />
                )}
              />
              <Button
                type="button"
                variant="outline"
                onClick={handleTestConnection}
                disabled={testConnectionMutation.isPending || !googleCalendarId}
              >
                {testConnectionMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : testConnectionMutation.isSuccess && testConnectionMutation.data.success ? (
                  <CheckCircle className="h-4 w-4 text-green-600" />
                ) : testConnectionMutation.isError ? (
                  <XCircle className="h-4 w-4 text-destructive" />
                ) : (
                  t('googleCalendarSync.testConnection')
                )}
              </Button>
            </div>
            {errors.google_calendar_id && (
              <p className="text-sm text-destructive">{errors.google_calendar_id.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="room_id">{t('googleCalendarSync.room')}</Label>
            <Controller
              name="room_id"
              control={control}
              render={({ field }) => (
                <Select
                  value={field.value?.toString() || 'null'}
                  onValueChange={(value) => field.onChange(value === 'null' ? null : parseInt(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('googleCalendarSync.selectRoom')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="null">{t('common.all')}</SelectItem>
                    {rooms?.map((room) => (
                      <SelectItem key={room.id} value={room.id.toString()}>
                        {room.name} ({room.site?.name || '-'})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="sync_direction">{t('googleCalendarSync.syncDirection')} *</Label>
            <Controller
              name="sync_direction"
              control={control}
              render={({ field }) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="import">{t('googleCalendarSync.direction.import')}</SelectItem>
                    <SelectItem value="export">{t('googleCalendarSync.direction.export')}</SelectItem>
                    <SelectItem value="both">{t('googleCalendarSync.direction.both')}</SelectItem>
                  </SelectContent>
                </Select>
              )}
            />
          </div>

          <div className="flex items-center space-x-2">
            <Controller
              name="sync_enabled"
              control={control}
              render={({ field }) => (
                <Switch id="sync_enabled" checked={field.value} onCheckedChange={field.onChange} />
              )}
            />
            <Label htmlFor="sync_enabled">{t('googleCalendarSync.syncEnabled')}</Label>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting || saveMutation.isPending}>
              {(isSubmitting || saveMutation.isPending) && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {config ? t('common.save') : t('common.create')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
