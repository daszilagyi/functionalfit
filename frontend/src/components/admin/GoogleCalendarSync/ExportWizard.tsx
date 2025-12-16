import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format, addDays } from 'date-fns'
import { Loader2, Upload, CheckCircle, AlertTriangle } from 'lucide-react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card } from '@/components/ui/card'
import { useToast } from '@/hooks/use-toast'
import { syncOperationsApi, googleCalendarSyncKeys } from '@/api/googleCalendarSync'
import { roomsApi } from '@/api/admin'
import type { GoogleCalendarSyncConfig, ExportEventsInput } from '@/types/googleCalendar'
import type { Room } from '@/types/admin'

interface ExportWizardProps {
  open: boolean
  onClose: () => void
  config: GoogleCalendarSyncConfig | null
}

type WizardStep = 'setup' | 'exporting' | 'results'

interface ExportResult {
  created: number
  updated: number
  skipped: number
  failed: number
  errors: Array<{ event_id: number; error: string }>
}

export function ExportWizard({ open, onClose, config }: ExportWizardProps) {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const [step, setStep] = useState<WizardStep>('setup')
  const [startDate, setStartDate] = useState<string>(format(new Date(), 'yyyy-MM-dd'))
  const [endDate, setEndDate] = useState<string>(format(addDays(new Date(), 30), 'yyyy-MM-dd'))
  const [selectedRoomId, setSelectedRoomId] = useState<number | null>(null)
  const [overwriteExisting, setOverwriteExisting] = useState(false)
  const [results, setResults] = useState<ExportResult | null>(null)

  // Fetch rooms
  const { data: rooms } = useQuery<Room[]>({
    queryKey: ['rooms'],
    queryFn: () => roomsApi.list(),
  })

  // Export mutation
  const exportMutation = useMutation({
    mutationFn: (data: ExportEventsInput) => syncOperationsApi.export(data),
    onSuccess: (result: { results: ExportResult }) => {
      setResults(result.results)
      setStep('results')
      toast({
        title: t('googleCalendarSync.exportCompleted'),
        description: `${result.results.created} ${t('googleCalendarSync.eventsCreated')}, ${result.results.updated} ${t('googleCalendarSync.eventsUpdated')}`,
      })
      queryClient.invalidateQueries({ queryKey: googleCalendarSyncKeys.logs() })
      queryClient.invalidateQueries({ queryKey: googleCalendarSyncKeys.configs() })
    },
    onError: (error: any) => {
      setStep('setup')
      toast({
        title: t('googleCalendarSync.exportFailed'),
        description: error.response?.data?.message || 'Error',
        variant: 'destructive',
      })
    },
  })

  useEffect(() => {
    if (!open) {
      setStep('setup')
      setResults(null)
      setOverwriteExisting(false)
    }
  }, [open])

  useEffect(() => {
    if (config?.room_id) {
      setSelectedRoomId(config.room_id)
    }
  }, [config])

  const handleStartExport = () => {
    if (!config) return

    const input: ExportEventsInput = {
      sync_config_id: config.id,
      start_date: startDate,
      end_date: endDate,
      room_id: selectedRoomId,
      overwrite_existing: overwriteExisting,
    }

    setStep('exporting')
    exportMutation.mutate(input)
  }

  const handleClose = () => {
    onClose()
  }

  if (!config) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Upload className="h-5 w-5" />
            {t('googleCalendarSync.exportWizard')}
          </DialogTitle>
          <DialogDescription>{config.name}</DialogDescription>
        </DialogHeader>

        {step === 'setup' && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t('googleCalendarSync.startDate')}</Label>
                <Input
                  type="date"
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                />
              </div>

              <div className="space-y-2">
                <Label>{t('googleCalendarSync.endDate')}</Label>
                <Input
                  type="date"
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>{t('googleCalendarSync.room')}</Label>
              <Select
                value={selectedRoomId?.toString() || 'null'}
                onValueChange={(value: string) => setSelectedRoomId(value === 'null' ? null : parseInt(value))}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t('googleCalendarSync.selectRoom')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="null">All Rooms</SelectItem>
                  {rooms?.map((room: Room) => (
                    <SelectItem key={room.id} value={room.id.toString()}>
                      {room.name} ({room.site?.name || '-'})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex items-center space-x-2">
              <Switch id="overwrite" checked={overwriteExisting} onCheckedChange={setOverwriteExisting} />
              <Label htmlFor="overwrite">{t('googleCalendarSync.overwriteExisting')}</Label>
            </div>

            <Card className="p-4 border-orange-200 bg-orange-50 dark:bg-orange-900/20">
              <div className="flex gap-2">
                <AlertTriangle className="h-5 w-5 text-orange-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-semibold text-orange-800 dark:text-orange-300">
                    {t('googleCalendarSync.exportWarning')}
                  </h4>
                  <p className="text-sm text-orange-700 dark:text-orange-400 mt-1">
                    {t('googleCalendarSync.exportWarningDescription')}
                  </p>
                </div>
              </div>
            </Card>
          </div>
        )}

        {step === 'exporting' && (
          <div className="space-y-4 py-8">
            <div className="flex flex-col items-center gap-4">
              <Loader2 className="h-12 w-12 animate-spin text-primary" />
              <p className="text-lg font-medium">{t('googleCalendarSync.exportingEvents')}</p>
              <p className="text-sm text-muted-foreground">{t('googleCalendarSync.pleaseWait')}</p>
            </div>
          </div>
        )}

        {step === 'results' && results && (
          <div className="space-y-4">
            <Card className="p-4 border-green-200 bg-green-50 dark:bg-green-900/20">
              <div className="flex gap-2">
                <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-semibold text-green-800 dark:text-green-300">
                    {t('googleCalendarSync.exportCompleted')}
                  </h4>
                  <p className="text-sm text-green-700 dark:text-green-400 mt-1">
                    {t('googleCalendarSync.exportCompletedDescription')}
                  </p>
                </div>
              </div>
            </Card>

            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                  <div className="text-2xl font-bold text-green-700 dark:text-green-400">{results.created}</div>
                  <div className="text-sm text-green-600 dark:text-green-500">
                    {t('googleCalendarSync.eventsCreated')}
                  </div>
                </div>

                <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                  <div className="text-2xl font-bold text-blue-700 dark:text-blue-400">{results.updated}</div>
                  <div className="text-sm text-blue-600 dark:text-blue-500">
                    {t('googleCalendarSync.eventsUpdated')}
                  </div>
                </div>

                <div className="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg border border-gray-200 dark:border-gray-800">
                  <div className="text-2xl font-bold text-gray-700 dark:text-gray-400">{results.skipped}</div>
                  <div className="text-sm text-gray-600 dark:text-gray-500">
                    {t('googleCalendarSync.eventsSkipped')}
                  </div>
                </div>

                <div className="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-200 dark:border-red-800">
                  <div className="text-2xl font-bold text-red-700 dark:text-red-400">{results.failed}</div>
                  <div className="text-sm text-red-600 dark:text-red-500">
                    {t('googleCalendarSync.eventsFailed')}
                  </div>
                </div>
              </div>

              {results.errors.length > 0 && (
                <Card className="p-4 border-red-200 bg-red-50 dark:bg-red-900/20">
                  <div className="flex gap-2">
                    <AlertTriangle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div>
                      <h4 className="font-semibold text-red-800 dark:text-red-300">
                        {t('googleCalendarSync.errorsOccurred')}
                      </h4>
                      <ul className="list-disc list-inside space-y-1 mt-2 text-sm text-red-700 dark:text-red-400">
                        {results.errors.slice(0, 5).map((error, idx) => (
                          <li key={idx}>
                            Event {error.event_id}: {error.error}
                          </li>
                        ))}
                        {results.errors.length > 5 && (
                          <li className="font-medium">
                            And {results.errors.length - 5} more...
                          </li>
                        )}
                      </ul>
                    </div>
                  </div>
                </Card>
              )}
            </div>
          </div>
        )}

        <DialogFooter>
          {step === 'setup' && (
            <>
              <Button variant="outline" onClick={handleClose}>
                Cancel
              </Button>
              <Button onClick={handleStartExport} disabled={exportMutation.isPending}>
                {exportMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {t('googleCalendarSync.startExport')}
              </Button>
            </>
          )}

          {step === 'exporting' && (
            <Button variant="outline" disabled>
              {t('googleCalendarSync.exporting')}
            </Button>
          )}

          {step === 'results' && <Button onClick={handleClose}>Close</Button>}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
