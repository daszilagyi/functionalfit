import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { X } from 'lucide-react'
import { calendarChangesApi, adminKeys } from '@/api/admin'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

interface CalendarChangeDetailModalProps {
  changeId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function CalendarChangeDetailModal({
  changeId,
  open,
  onOpenChange,
}: CalendarChangeDetailModalProps) {
  const { t, i18n } = useTranslation('admin')
  const locale = i18n.language === 'hu' ? hu : enUS

  const { data: change, isLoading } = useQuery({
    queryKey: adminKeys.calendarChangeDetail(changeId!),
    queryFn: () => calendarChangesApi.getDetail(changeId!),
    enabled: !!changeId && open,
  })

  const getActionColor = (action: string) => {
    const colors = {
      EVENT_CREATED: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      EVENT_UPDATED: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      EVENT_DELETED: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }
    return colors[action as keyof typeof colors] || 'bg-gray-100 text-gray-800'
  }

  const formatValue = (value: unknown): string => {
    if (value === null || value === undefined) {
      return '-'
    }
    if (typeof value === 'boolean') {
      return value ? t('common:yes', 'Yes') : t('common:no', 'No')
    }
    if (typeof value === 'object') {
      return JSON.stringify(value, null, 2)
    }
    // Try to format as date if it looks like ISO string
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
      try {
        return format(new Date(value), 'PPp', { locale })
      } catch {
        return String(value)
      }
    }
    return String(value)
  }

  const renderFieldComparison = (field: string, before: unknown, after: unknown, isChanged: boolean) => {
    return (
      <div
        key={field}
        className={cn(
          'grid grid-cols-2 gap-4 py-2 border-b border-gray-100 dark:border-gray-800',
          isChanged && 'bg-yellow-50 dark:bg-yellow-900/10'
        )}
      >
        <div className="px-3">
          <div className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
            {field}
          </div>
          <div className="text-sm text-gray-900 dark:text-white whitespace-pre-wrap font-mono">
            {formatValue(before)}
          </div>
        </div>
        <div className="px-3">
          <div className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
            {field}
          </div>
          <div className="text-sm text-gray-900 dark:text-white whitespace-pre-wrap font-mono">
            {formatValue(after)}
          </div>
        </div>
      </div>
    )
  }

  const renderChanges = () => {
    if (!change) return null

    if (change.action === 'EVENT_CREATED') {
      // Show only "after" state
      return (
        <div className="space-y-2">
          <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
            {t('calendarChanges.detail.after', 'After')}
          </h4>
          {change.after && Object.entries(change.after).map(([key, value]) => (
            <div key={key} className="py-2 border-b border-gray-100 dark:border-gray-800">
              <div className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                {key}
              </div>
              <div className="text-sm text-gray-900 dark:text-white whitespace-pre-wrap font-mono">
                {formatValue(value)}
              </div>
            </div>
          ))}
        </div>
      )
    }

    if (change.action === 'EVENT_DELETED') {
      // Show only "before" state
      return (
        <div className="space-y-2">
          <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
            {t('calendarChanges.detail.before', 'Before')}
          </h4>
          {change.before && Object.entries(change.before).map(([key, value]) => (
            <div key={key} className="py-2 border-b border-gray-100 dark:border-gray-800">
              <div className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                {key}
              </div>
              <div className="text-sm text-gray-900 dark:text-white whitespace-pre-wrap font-mono">
                {formatValue(value)}
              </div>
            </div>
          ))}
        </div>
      )
    }

    // For EVENT_UPDATED, show side-by-side comparison
    if (!change.before || !change.after) {
      return (
        <div className="text-center text-gray-500 dark:text-gray-400 py-8">
          {t('calendarChanges.detail.noChanges', 'No changes detected')}
        </div>
      )
    }

    const allFields = new Set([
      ...Object.keys(change.before),
      ...Object.keys(change.after),
    ])

    const changedFieldsSet = new Set(change.changed_fields || [])

    return (
      <div className="space-y-2">
        <div className="grid grid-cols-2 gap-4 pb-2 border-b-2 border-gray-200 dark:border-gray-700">
          <div className="px-3">
            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
              {t('calendarChanges.detail.before', 'Before')}
            </h4>
          </div>
          <div className="px-3">
            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
              {t('calendarChanges.detail.after', 'After')}
            </h4>
          </div>
        </div>
        {Array.from(allFields).map((field) =>
          renderFieldComparison(
            field,
            (change.before as Record<string, unknown>)?.[field],
            (change.after as Record<string, unknown>)?.[field],
            changedFieldsSet.has(field)
          )
        )}
      </div>
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[900px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center justify-between">
            <DialogTitle>
              {t('calendarChanges.detail.title', 'Change Details')}
            </DialogTitle>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => onOpenChange(false)}
              data-testid="calendar-change-detail-close-btn"
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        </DialogHeader>

        {isLoading && (
          <div className="text-center py-12">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p className="mt-2 text-gray-600 dark:text-gray-400">{t('common:loading', 'Loading...')}</p>
          </div>
        )}

        {change && (
          <div className="space-y-6">
            {/* Metadata Section */}
            <div className="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-3">
              <div className="flex items-center gap-3">
                <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                  {t('calendarChanges.table.action', 'Action')}:
                </span>
                <Badge className={cn('text-xs font-semibold', getActionColor(change.action))}>
                  {t(`calendarChanges.actions.${change.action.toLowerCase().replace('event_', '')}`, change.action)}
                </Badge>
              </div>

              <div className="flex items-center gap-3">
                <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                  {t('calendarChanges.table.timestamp', 'Timestamp')}:
                </span>
                <span className="text-sm text-gray-900 dark:text-white">
                  {format(new Date(change.changed_at), 'PPp', { locale })}
                </span>
              </div>

              <div className="flex items-center gap-3">
                <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                  {t('calendarChanges.table.staff', 'Staff')}:
                </span>
                <span className="text-sm text-gray-900 dark:text-white">
                  {change.actor.name} ({change.actor.role})
                </span>
              </div>

              {change.site && (
                <div className="flex items-center gap-3">
                  <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {t('calendarChanges.table.site', 'Site')}:
                  </span>
                  <span className="text-sm text-gray-900 dark:text-white">{change.site}</span>
                </div>
              )}

              {change.room && (
                <div className="flex items-center gap-3">
                  <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {t('calendarChanges.table.room', 'Room')}:
                  </span>
                  <span className="text-sm text-gray-900 dark:text-white">{change.room.name}</span>
                </div>
              )}

              {change.event_time && (
                <div className="flex items-center gap-3">
                  <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {t('calendarChanges.table.eventTime', 'Event Time')}:
                  </span>
                  <span className="text-sm text-gray-900 dark:text-white">
                    {format(new Date(change.event_time.starts_at), 'PPp', { locale })} -{' '}
                    {format(new Date(change.event_time.ends_at), 'p', { locale })}
                  </span>
                </div>
              )}

              {change.ip_address && (
                <div className="flex items-center gap-3">
                  <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {t('calendarChanges.detail.ipAddress', 'IP Address')}:
                  </span>
                  <span className="text-sm text-gray-900 dark:text-white font-mono">{change.ip_address}</span>
                </div>
              )}

              {change.user_agent && (
                <div className="flex items-start gap-3">
                  <span className="text-sm font-medium text-gray-600 dark:text-gray-400 pt-1">
                    {t('calendarChanges.detail.userAgent', 'User Agent')}:
                  </span>
                  <span className="text-sm text-gray-900 dark:text-white font-mono break-all flex-1">
                    {change.user_agent}
                  </span>
                </div>
              )}

              {change.changed_fields && change.changed_fields.length > 0 && (
                <div className="flex items-start gap-3">
                  <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {t('calendarChanges.detail.changedFields', 'Changed Fields')}:
                  </span>
                  <div className="flex flex-wrap gap-1">
                    {change.changed_fields.map((field) => (
                      <Badge key={field} variant="outline" className="text-xs">
                        {field}
                      </Badge>
                    ))}
                  </div>
                </div>
              )}
            </div>

            {/* Changes Section */}
            <div className="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
              {renderChanges()}
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
