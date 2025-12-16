import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { Edit, Trash2, Download, Upload, ArrowLeftRight, Calendar, MapPin, CheckCircle, XCircle } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { GoogleCalendarSyncConfig } from '@/types/googleCalendar'

interface SyncConfigListProps {
  configs: GoogleCalendarSyncConfig[]
  isLoading: boolean
  onEdit: (config: GoogleCalendarSyncConfig) => void
  onDelete: (id: number) => void
  onImport: (config: GoogleCalendarSyncConfig) => void
  onExport: (config: GoogleCalendarSyncConfig) => void
}

export function SyncConfigList({ configs, isLoading, onEdit, onDelete, onImport, onExport }: SyncConfigListProps) {
  const { t } = useTranslation(['admin', 'common'])

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('googleCalendarSync.configurations')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-20 w-full" />
            ))}
          </div>
        </CardContent>
      </Card>
    )
  }

  if (configs.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('googleCalendarSync.configurations')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-12">
            <Calendar className="mx-auto h-12 w-12 text-muted-foreground" />
            <h3 className="mt-4 text-lg font-semibold">{t('googleCalendarSync.noConfigs')}</h3>
            <p className="mt-2 text-sm text-muted-foreground">
              {t('googleCalendarSync.noConfigsDescription')}
            </p>
          </div>
        </CardContent>
      </Card>
    )
  }

  const getSyncDirectionIcon = (direction: string) => {
    switch (direction) {
      case 'import':
        return <Download className="h-4 w-4" />
      case 'export':
        return <Upload className="h-4 w-4" />
      case 'both':
        return <ArrowLeftRight className="h-4 w-4" />
      default:
        return null
    }
  }

  const getSyncDirectionBadgeVariant = (direction: string) => {
    switch (direction) {
      case 'import':
        return 'default' as const
      case 'export':
        return 'secondary' as const
      case 'both':
        return 'outline' as const
      default:
        return 'outline' as const
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('googleCalendarSync.configurations')}</CardTitle>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('googleCalendarSync.name')}</TableHead>
              <TableHead>{t('googleCalendarSync.calendarId')}</TableHead>
              <TableHead>{t('googleCalendarSync.room')}</TableHead>
              <TableHead>{t('googleCalendarSync.syncDirection')}</TableHead>
              <TableHead>{t('googleCalendarSync.syncStatus')}</TableHead>
              <TableHead>{t('googleCalendarSync.lastSync')}</TableHead>
              <TableHead className="text-right">{t('common:actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {configs.map((config) => (
              <TableRow key={config.id}>
                <TableCell className="font-medium">{config.name}</TableCell>
                <TableCell>
                  <code className="text-xs bg-muted px-2 py-1 rounded">{config.google_calendar_id}</code>
                </TableCell>
                <TableCell>
                  {config.room ? (
                    <div className="flex items-center gap-1">
                      <MapPin className="h-3 w-3 text-muted-foreground" />
                      <span className="text-sm">{config.room.name}</span>
                    </div>
                  ) : (
                    <span className="text-sm text-muted-foreground">{t('common:all')}</span>
                  )}
                </TableCell>
                <TableCell>
                  <Badge variant={getSyncDirectionBadgeVariant(config.sync_direction)} className="gap-1">
                    {getSyncDirectionIcon(config.sync_direction)}
                    {t(`googleCalendarSync.direction.${config.sync_direction}`)}
                  </Badge>
                </TableCell>
                <TableCell>
                  {config.sync_enabled ? (
                    <Badge variant="default" className="gap-1">
                      <CheckCircle className="h-3 w-3" />
                      {t('common:enabled')}
                    </Badge>
                  ) : (
                    <Badge variant="secondary" className="gap-1">
                      <XCircle className="h-3 w-3" />
                      {t('common:disabled')}
                    </Badge>
                  )}
                </TableCell>
                <TableCell>
                  <div className="text-sm space-y-1">
                    {config.last_import_at && (
                      <div className="flex items-center gap-1 text-muted-foreground">
                        <Download className="h-3 w-3" />
                        <span>{format(new Date(config.last_import_at), 'yyyy-MM-dd HH:mm')}</span>
                      </div>
                    )}
                    {config.last_export_at && (
                      <div className="flex items-center gap-1 text-muted-foreground">
                        <Upload className="h-3 w-3" />
                        <span>{format(new Date(config.last_export_at), 'yyyy-MM-dd HH:mm')}</span>
                      </div>
                    )}
                    {!config.last_import_at && !config.last_export_at && (
                      <span className="text-muted-foreground">{t('common:never')}</span>
                    )}
                  </div>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    {config.sync_enabled && (config.sync_direction === 'import' || config.sync_direction === 'both') && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onImport(config)}
                        title={t('googleCalendarSync.import')}
                      >
                        <Download className="h-4 w-4" />
                      </Button>
                    )}
                    {config.sync_enabled && (config.sync_direction === 'export' || config.sync_direction === 'both') && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onExport(config)}
                        title={t('googleCalendarSync.export')}
                      >
                        <Upload className="h-4 w-4" />
                      </Button>
                    )}
                    <Button variant="ghost" size="sm" onClick={() => onEdit(config)} title={t('common:edit')}>
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => onDelete(config.id)}
                      title={t('common:delete')}
                      className="text-destructive hover:text-destructive"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}
