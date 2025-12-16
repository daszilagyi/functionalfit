import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { format, addDays } from 'date-fns'
import { Loader2, Download, AlertTriangle, CheckCircle } from 'lucide-react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { useToast } from '@/hooks/use-toast'
import { syncOperationsApi, syncLogsApi, googleCalendarSyncKeys } from '@/api/googleCalendarSync'
import { roomsApi } from '@/api/admin'
import type { GoogleCalendarSyncConfig, ImportEventsInput, GoogleCalendarConflict, GoogleCalendarSyncLog } from '@/types/googleCalendar'
import type { Room } from '@/types/admin'

interface ImportWizardProps {
  open: boolean
  onClose: () => void
  config: GoogleCalendarSyncConfig | null
}

type WizardStep = 'setup' | 'conflicts' | 'results'

export function ImportWizard({ open, onClose, config }: ImportWizardProps) {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const [step, setStep] = useState<WizardStep>('setup')
  const [startDate, setStartDate] = useState<string>(format(new Date(), 'yyyy-MM-dd'))
  const [endDate, setEndDate] = useState<string>(format(addDays(new Date(), 30), 'yyyy-MM-dd'))
  const [selectedRoomId, setSelectedRoomId] = useState<number | null>(null)
  const [autoResolve, setAutoResolve] = useState(false)
  const [conflicts, setConflicts] = useState<GoogleCalendarConflict[]>([])
  const [conflictResolutions, setConflictResolutions] = useState<Record<string, 'overwrite' | 'skip'>>({})
  const [logId, setLogId] = useState<number | null>(null)
  const [importResults, setImportResults] = useState<GoogleCalendarSyncLog | null>(null)

  // Fetch rooms
  const { data: rooms } = useQuery<Room[]>({
    queryKey: ['rooms'],
    queryFn: () => roomsApi.list(),
  })

  // Import mutation
  const importMutation = useMutation({
    mutationFn: (data: ImportEventsInput) => syncOperationsApi.import(data),
    onSuccess: (result) => {
      const log = result.data
      setLogId(log.id)

      if (log.conflicts && log.conflicts.length > 0 && !autoResolve) {
        setConflicts(log.conflicts)
        setStep('conflicts')
        toast({
          title: t('googleCalendarSync.conflictsDetected'),
          description: t('googleCalendarSync.conflictsDetectedDescription', { count: log.conflicts.length }),
          variant: 'destructive',
        })
      } else {
        setImportResults(log)
        setStep('results')
        toast({
          title: t('googleCalendarSync.importCompleted'),
          description: `${log.events_created} ${t('googleCalendarSync.eventsCreated')}, ${log.events_updated} ${t('googleCalendarSync.eventsUpdated')}`,
        })
      }

      queryClient.invalidateQueries({ queryKey: googleCalendarSyncKeys.logs() })
      queryClient.invalidateQueries({ queryKey: googleCalendarSyncKeys.configs() })
    },
    onError: (error: any) => {
      setStep('setup')
      toast({
        title: t('googleCalendarSync.importFailed'),
        description: error.response?.data?.message || 'Error',
        variant: 'destructive',
      })
    },
  })

  // Resolve conflicts mutation
  const resolveMutation = useMutation({
    mutationFn: (data: { log_id: number; resolutions: Record<string, 'overwrite' | 'skip'> }) =>
      syncLogsApi.resolveConflicts(data.log_id, { resolutions: data.resolutions }),
    onSuccess: (result: GoogleCalendarSyncLog) => {
      setImportResults(result)
      setStep('results')
      toast({
        title: t('googleCalendarSync.conflictsResolved'),
        description: t('googleCalendarSync.conflictsResolvedDescription'),
      })
      queryClient.invalidateQueries({ queryKey: googleCalendarSyncKeys.logs() })
    },
    onError: (error: any) => {
      toast({
        title: t('googleCalendarSync.resolveError'),
        description: error.response?.data?.message || 'Error',
        variant: 'destructive',
      })
    },
  })

  useEffect(() => {
    if (!open) {
      setStep('setup')
      setConflicts([])
      setConflictResolutions({})
      setLogId(null)
      setImportResults(null)
      setAutoResolve(false)
    }
  }, [open])

  useEffect(() => {
    if (config?.room_id) {
      setSelectedRoomId(config.room_id)
    }
  }, [config])

  const handleStartImport = () => {
    if (!config) return

    const input: ImportEventsInput = {
      sync_config_id: config.id,
      start_date: startDate,
      end_date: endDate,
      room_id: selectedRoomId,
      auto_resolve_conflicts: autoResolve,
    }

    importMutation.mutate(input)
  }

  const handleResolveConflicts = () => {
    if (!logId) return
    resolveMutation.mutate({ log_id: logId, resolutions: conflictResolutions })
  }

  const handleSetResolution = (googleEventId: string, resolution: 'overwrite' | 'skip') => {
    setConflictResolutions((prev) => ({
      ...prev,
      [googleEventId]: resolution,
    }))
  }

  const handleClose = () => {
    onClose()
  }

  if (!config) return null

  const allConflictsResolved = conflicts.length > 0 && conflicts.every((c) => conflictResolutions[c.google_event_id])

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Download className="h-5 w-5" />
            {t('googleCalendarSync.importWizard')}
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
              <Switch id="auto-resolve" checked={autoResolve} onCheckedChange={setAutoResolve} />
              <Label htmlFor="auto-resolve">{t('googleCalendarSync.autoResolveConflicts')}</Label>
            </div>

            <Card className="p-4 border-blue-200 bg-blue-50 dark:bg-blue-900/20">
              <div className="flex gap-2">
                <AlertTriangle className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-semibold text-blue-800 dark:text-blue-300">
                    {t('googleCalendarSync.importWarning')}
                  </h4>
                  <p className="text-sm text-blue-700 dark:text-blue-400 mt-1">
                    {t('googleCalendarSync.importWarningDescription')}
                  </p>
                </div>
              </div>
            </Card>
          </div>
        )}

        {step === 'conflicts' && (
          <div className="space-y-4">
            <Card className="p-4 border-orange-200 bg-orange-50 dark:bg-orange-900/20">
              <div className="flex gap-2">
                <AlertTriangle className="h-5 w-5 text-orange-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-semibold text-orange-800 dark:text-orange-300">
                    {t('googleCalendarSync.conflictsDetected')}
                  </h4>
                  <p className="text-sm text-orange-700 dark:text-orange-400 mt-1">
                    {t('googleCalendarSync.conflictsDetectedDescription', { count: conflicts.length })}
                  </p>
                </div>
              </div>
            </Card>

            <div className="space-y-3">
              <h4 className="font-semibold">{t('googleCalendarSync.conflictingEvents')}</h4>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Google Event</TableHead>
                    <TableHead>Conflict</TableHead>
                    <TableHead>Resolution</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {conflicts.map((conflict) => (
                    <TableRow key={conflict.google_event_id}>
                      <TableCell>
                        <div>
                          <div className="font-medium">{conflict.google_summary}</div>
                          <div className="text-sm text-muted-foreground">
                            {format(new Date(conflict.google_start), 'PPP p')}
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="destructive">
                          {conflict.conflicting_events[0]?.overlap_minutes || 0} {t('googleCalendarSync.minutesOverlap')}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Select
                          value={conflictResolutions[conflict.google_event_id] || ''}
                          onValueChange={(value: 'overwrite' | 'skip') =>
                            handleSetResolution(conflict.google_event_id, value)
                          }
                        >
                          <SelectTrigger className="w-32">
                            <SelectValue placeholder="Choose..." />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="skip">Skip</SelectItem>
                            <SelectItem value="overwrite">Overwrite</SelectItem>
                          </SelectContent>
                        </Select>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        )}

        {step === 'results' && importResults && (
          <div className="space-y-4">
            <Card className="p-4 border-green-200 bg-green-50 dark:bg-green-900/20">
              <div className="flex gap-2">
                <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-semibold text-green-800 dark:text-green-300">
                    {t('googleCalendarSync.importCompleted')}
                  </h4>
                  <p className="text-sm text-green-700 dark:text-green-400 mt-1">
                    {t('googleCalendarSync.importCompletedDescription')}
                  </p>
                </div>
              </div>
            </Card>

            <div className="grid grid-cols-2 gap-3">
              <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                <div className="text-2xl font-bold text-green-700 dark:text-green-400">
                  {importResults.events_created}
                </div>
                <div className="text-sm text-green-600 dark:text-green-500">
                  {t('googleCalendarSync.eventsCreated')}
                </div>
              </div>

              <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                <div className="text-2xl font-bold text-blue-700 dark:text-blue-400">
                  {importResults.events_updated}
                </div>
                <div className="text-sm text-blue-600 dark:text-blue-500">
                  {t('googleCalendarSync.eventsUpdated')}
                </div>
              </div>

              <div className="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg border border-gray-200 dark:border-gray-800">
                <div className="text-2xl font-bold text-gray-700 dark:text-gray-400">
                  {importResults.events_skipped}
                </div>
                <div className="text-sm text-gray-600 dark:text-gray-500">
                  {t('googleCalendarSync.eventsSkipped')}
                </div>
              </div>

              <div className="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-200 dark:border-red-800">
                <div className="text-2xl font-bold text-red-700 dark:text-red-400">
                  {importResults.events_failed}
                </div>
                <div className="text-sm text-red-600 dark:text-red-500">
                  {t('googleCalendarSync.eventsFailed')}
                </div>
              </div>
            </div>
          </div>
        )}

        <DialogFooter>
          {step === 'setup' && (
            <>
              <Button variant="outline" onClick={handleClose}>
                Cancel
              </Button>
              <Button onClick={handleStartImport} disabled={importMutation.isPending}>
                {importMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {t('googleCalendarSync.startImport')}
              </Button>
            </>
          )}

          {step === 'conflicts' && (
            <>
              <Button variant="outline" onClick={() => setStep('setup')}>
                Back
              </Button>
              <Button
                onClick={handleResolveConflicts}
                disabled={!allConflictsResolved || resolveMutation.isPending}
              >
                {resolveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {t('googleCalendarSync.resolveConflicts')}
              </Button>
            </>
          )}

          {step === 'results' && <Button onClick={handleClose}>Close</Button>}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
