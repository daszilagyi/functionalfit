import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { format, parseISO } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { PublicClassOccurrence } from '@/types/public'
import { PublicClassCard } from './PublicClassCard'

interface PublicClassListViewProps {
  classes: PublicClassOccurrence[]
}

export function PublicClassListView({ classes }: PublicClassListViewProps) {
  const { t, i18n } = useTranslation('public')
  const locale = i18n.language === 'hu' ? hu : enUS

  // Group classes by date
  const groupedByDate = useMemo(() => {
    const groups: Record<string, PublicClassOccurrence[]> = {}

    classes.forEach((classOccurrence) => {
      const date = format(parseISO(classOccurrence.starts_at), 'yyyy-MM-dd')
      if (!groups[date]) {
        groups[date] = []
      }
      groups[date].push(classOccurrence)
    })

    // Sort within each group by start time
    Object.keys(groups).forEach((date) => {
      groups[date].sort((a, b) =>
        a.starts_at.localeCompare(b.starts_at)
      )
    })

    return groups
  }, [classes])

  const sortedDates = Object.keys(groupedByDate).sort()

  if (classes.length === 0) {
    return (
      <div className="text-center py-12 text-muted-foreground">
        {t('publicClasses.noClasses')}
      </div>
    )
  }

  return (
    <div className="space-y-8" data-testid="class-list-view">
      {sortedDates.map((date) => {
        const dateObj = parseISO(date)
        const dateLabel = format(dateObj, 'EEEE, MMMM d, yyyy', { locale })

        return (
          <div key={date} className="space-y-4">
            <h2 className="text-xl font-semibold sticky top-0 bg-background py-2 z-10 border-b">
              {dateLabel}
            </h2>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {groupedByDate[date].map((classOccurrence) => (
                <PublicClassCard
                  key={classOccurrence.id}
                  classOccurrence={classOccurrence}
                />
              ))}
            </div>
          </div>
        )
      })}
    </div>
  )
}
