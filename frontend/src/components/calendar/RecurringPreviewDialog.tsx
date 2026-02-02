import { useState, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { format, parseISO } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { CheckCircle, XCircle } from 'lucide-react'
import type { RecurringPreviewDate } from '@/types/event'

interface RecurringPreviewDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  dates: RecurringPreviewDate[]
  onConfirm: (skipDates: string[]) => void
  onCancel: () => void
  isPending?: boolean
}

export function RecurringPreviewDialog({
  open,
  onOpenChange,
  dates,
  onConfirm,
  onCancel,
  isPending = false,
}: RecurringPreviewDialogProps) {
  const { t, i18n } = useTranslation('calendar')
  const locale = i18n.language === 'hu' ? hu : enUS

  // Track which dates to skip (initially all conflicts are checked for skipping)
  const [skipDates, setSkipDates] = useState<Set<string>>(() => {
    return new Set(dates.filter(d => d.status === 'conflict').map(d => d.date))
  })

  // Calculate counts
  const stats = useMemo(() => {
    const totalOk = dates.filter(d => d.status === 'ok').length
    const totalConflict = dates.filter(d => d.status === 'conflict').length
    const willBeCreated = dates.filter(d => !skipDates.has(d.date)).length
    const willBeSkipped = skipDates.size
    return { totalOk, totalConflict, willBeCreated, willBeSkipped }
  }, [dates, skipDates])

  const handleToggleSkip = (date: string) => {
    setSkipDates(prev => {
      const next = new Set(prev)
      if (next.has(date)) {
        next.delete(date)
      } else {
        next.add(date)
      }
      return next
    })
  }

  const handleConfirm = () => {
    onConfirm(Array.from(skipDates))
  }

  const handleCancel = () => {
    onCancel()
  }

  // Check if all dates would be skipped
  const allSkipped = stats.willBeCreated === 0

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
        <AlertDialogHeader>
          <AlertDialogTitle>{t('recurring.preview.title')}</AlertDialogTitle>
          <AlertDialogDescription>
            {stats.totalConflict === 0
              ? t('recurring.preview.noConflicts')
              : t('recurring.preview.description')}
          </AlertDialogDescription>
        </AlertDialogHeader>

        <div className="py-4">
          <div className="border rounded-lg overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-muted">
                <tr>
                  <th className="px-4 py-2 text-left font-medium">{t('recurring.preview.date')}</th>
                  <th className="px-4 py-2 text-left font-medium">{t('recurring.preview.status')}</th>
                  <th className="px-4 py-2 text-left font-medium">{t('recurring.preview.conflictWith')}</th>
                  <th className="px-4 py-2 text-center font-medium">{t('recurring.preview.skip')}</th>
                </tr>
              </thead>
              <tbody>
                {dates.map((dateEntry) => {
                  const isSkipped = skipDates.has(dateEntry.date)
                  const dateObj = parseISO(dateEntry.date)
                  const formattedDate = format(dateObj, 'yyyy. MM. dd. (EEEE)', { locale })

                  return (
                    <tr
                      key={dateEntry.date}
                      className={`border-t ${isSkipped ? 'bg-muted/50 text-muted-foreground' : ''}`}
                    >
                      <td className="px-4 py-2">{formattedDate}</td>
                      <td className="px-4 py-2">
                        {dateEntry.status === 'ok' ? (
                          <span className="inline-flex items-center gap-1 text-green-600">
                            <CheckCircle className="h-4 w-4" />
                            {t('recurring.preview.ok')}
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 text-red-600">
                            <XCircle className="h-4 w-4" />
                            {t('recurring.preview.conflict')}
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-2 text-muted-foreground">
                        {dateEntry.conflict_with || '-'}
                      </td>
                      <td className="px-4 py-2 text-center">
                        <Checkbox
                          checked={isSkipped}
                          onCheckedChange={() => handleToggleSkip(dateEntry.date)}
                          disabled={isPending}
                        />
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {/* Summary */}
          <div className="mt-4 p-3 bg-muted rounded-lg text-sm">
            <div className="flex justify-between">
              <span>{t('recurring.preview.date')}:</span>
              <span className="font-medium">{dates.length}</span>
            </div>
            <div className="flex justify-between mt-1">
              <span className="text-green-600">{t('recurring.preview.ok')}:</span>
              <span className="font-medium">{stats.totalOk}</span>
            </div>
            <div className="flex justify-between mt-1">
              <span className="text-red-600">{t('recurring.preview.conflict')}:</span>
              <span className="font-medium">{stats.totalConflict}</span>
            </div>
            <div className="border-t mt-2 pt-2 flex justify-between">
              <span>{t('actions.create')}:</span>
              <span className="font-medium">{stats.willBeCreated}</span>
            </div>
          </div>

          {allSkipped && (
            <p className="mt-4 text-sm text-destructive font-medium">
              {t('recurring.preview.allConflicts')}
            </p>
          )}
        </div>

        <AlertDialogFooter>
          <Button variant="outline" onClick={handleCancel} disabled={isPending}>
            {t('actions.cancel')}
          </Button>
          <Button onClick={handleConfirm} disabled={isPending || allSkipped}>
            {isPending
              ? t('common.loading')
              : stats.willBeCreated === dates.length
                ? t('recurring.preview.confirm')
                : t('recurring.preview.confirmWithSkips', { count: stats.willBeCreated })}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
