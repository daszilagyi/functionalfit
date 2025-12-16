import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { Download, Upload, CheckCircle, XCircle, Clock, AlertTriangle, Loader2 } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { GoogleCalendarSyncLog } from '@/types/googleCalendar'

interface SyncLogsViewerProps {
  logs: GoogleCalendarSyncLog[]
  isLoading: boolean
  pagination?: {
    current_page: number
    total: number
    per_page: number
  }
}

export function SyncLogsViewer({ logs, isLoading, pagination }: SyncLogsViewerProps) {
  const { t } = useTranslation('admin')

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('googleCalendarSync.syncLogs')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <Skeleton key={i} className="h-16 w-full" />
            ))}
          </div>
        </CardContent>
      </Card>
    )
  }

  if (logs.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('googleCalendarSync.syncLogs')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-12">
            <Clock className="mx-auto h-12 w-12 text-muted-foreground" />
            <h3 className="mt-4 text-lg font-semibold">{t('googleCalendarSync.noLogs')}</h3>
            <p className="mt-2 text-sm text-muted-foreground">{t('googleCalendarSync.noLogsDescription')}</p>
          </div>
        </CardContent>
      </Card>
    )
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="h-4 w-4 text-green-600" />
      case 'failed':
        return <XCircle className="h-4 w-4 text-red-600" />
      case 'in_progress':
        return <Loader2 className="h-4 w-4 text-blue-600 animate-spin" />
      case 'pending':
        return <Clock className="h-4 w-4 text-gray-600" />
      case 'cancelled':
        return <XCircle className="h-4 w-4 text-gray-600" />
      default:
        return null
    }
  }

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'completed':
        return 'default' as const
      case 'failed':
        return 'destructive' as const
      case 'in_progress':
        return 'secondary' as const
      default:
        return 'outline' as const
    }
  }

  const getOperationIcon = (operation: string) => {
    return operation === 'import' ? <Download className="h-4 w-4" /> : <Upload className="h-4 w-4" />
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('googleCalendarSync.syncLogs')}</CardTitle>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('googleCalendarSync.operation')}</TableHead>
              <TableHead>{t('googleCalendarSync.configuration')}</TableHead>
              <TableHead>{t('googleCalendarSync.status')}</TableHead>
              <TableHead>{t('googleCalendarSync.dateRange')}</TableHead>
              <TableHead>{t('googleCalendarSync.results')}</TableHead>
              <TableHead>{t('googleCalendarSync.conflicts')}</TableHead>
              <TableHead>{t('googleCalendarSync.completed')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {logs.map((log) => (
              <TableRow key={log.id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    {getOperationIcon(log.operation)}
                    <span className="capitalize">{t(`googleCalendarSync.operation.${log.operation}`)}</span>
                  </div>
                </TableCell>
                <TableCell>
                  <div>
                    <div className="font-medium">{log.sync_config?.name}</div>
                    {log.sync_config?.room && (
                      <div className="text-xs text-muted-foreground">{log.sync_config.room.name}</div>
                    )}
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant={getStatusBadgeVariant(log.status)} className="gap-1">
                    {getStatusIcon(log.status)}
                    {t(`googleCalendarSync.status.${log.status}`)}
                  </Badge>
                </TableCell>
                <TableCell>
                  {log.filters?.start_date && log.filters?.end_date && (
                    <div className="text-sm">
                      <div>{format(new Date(log.filters.start_date), 'yyyy-MM-dd')}</div>
                      <div className="text-muted-foreground">{format(new Date(log.filters.end_date), 'yyyy-MM-dd')}</div>
                    </div>
                  )}
                </TableCell>
                <TableCell>
                  <div className="text-sm space-y-1">
                    {log.events_created > 0 && (
                      <div className="text-green-600">
                        +{log.events_created} {t('googleCalendarSync.created')}
                      </div>
                    )}
                    {log.events_updated > 0 && (
                      <div className="text-blue-600">
                        ~{log.events_updated} {t('googleCalendarSync.updated')}
                      </div>
                    )}
                    {log.events_skipped > 0 && (
                      <div className="text-gray-600">
                        -{log.events_skipped} {t('googleCalendarSync.skipped')}
                      </div>
                    )}
                    {log.events_failed > 0 && (
                      <div className="text-red-600">
                        ✗{log.events_failed} {t('googleCalendarSync.failed')}
                      </div>
                    )}
                    {log.events_processed === 0 && (
                      <span className="text-muted-foreground">{t('googleCalendarSync.noEvents')}</span>
                    )}
                  </div>
                </TableCell>
                <TableCell>
                  {log.conflicts_detected > 0 ? (
                    <Badge variant="destructive" className="gap-1">
                      <AlertTriangle className="h-3 w-3" />
                      {log.conflicts_detected}
                    </Badge>
                  ) : (
                    <span className="text-muted-foreground">-</span>
                  )}
                </TableCell>
                <TableCell>
                  {log.completed_at ? (
                    <div className="text-sm">
                      <div>{format(new Date(log.completed_at), 'yyyy-MM-dd')}</div>
                      <div className="text-muted-foreground">{format(new Date(log.completed_at), 'HH:mm:ss')}</div>
                    </div>
                  ) : (
                    <span className="text-muted-foreground">-</span>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        {pagination && (
          <div className="mt-4 text-sm text-muted-foreground text-center">
            {t('common.showing')} {logs.length} {t('common.of')} {pagination.total} {t('common.results')}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
