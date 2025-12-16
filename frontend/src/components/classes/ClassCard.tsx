import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu } from 'date-fns/locale'
import { ClassOccurrence } from '@/types/class'
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ClassDetailsModal } from './ClassDetailsModal'

interface ClassCardProps {
  classOccurrence: ClassOccurrence
}

export function ClassCard({ classOccurrence }: ClassCardProps) {
  const { t, i18n } = useTranslation('classes')
  const [detailsOpen, setDetailsOpen] = useState(false)

  const locale = i18n.language === 'hu' ? hu : undefined
  const startTime = format(new Date(classOccurrence.starts_at), 'HH:mm', { locale })
  const endTime = format(new Date(classOccurrence.ends_at), 'HH:mm', { locale })
  const date = format(new Date(classOccurrence.starts_at), 'MMM d, yyyy', { locale })

  // Use backend-provided values, with fallback calculation
  const spotsLeft = classOccurrence.available_spots ??
    ((classOccurrence.capacity ?? classOccurrence.class_template?.capacity ?? 0) - (classOccurrence.booked_count ?? classOccurrence.registered_count ?? 0))
  const isFull = classOccurrence.is_full ?? spotsLeft <= 0

  return (
    <>
      <Card
        className="hover:shadow-lg transition-shadow cursor-pointer"
        onClick={() => setDetailsOpen(true)}
        data-testid={`class-card-${classOccurrence.id}`}
      >
        <CardHeader>
          <div className="flex justify-between items-start">
            <CardTitle>{classOccurrence.class_template?.title}</CardTitle>
            {isFull ? (
              <Badge variant="destructive">{t('fullyBooked')}</Badge>
            ) : (
              <Badge variant="secondary">{t('spotsLeft', { count: spotsLeft })}</Badge>
            )}
          </div>
          <CardDescription>
            {date} • {startTime} - {endTime}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-muted-foreground">{t('trainer')}:</span>
              <span>{classOccurrence.trainer?.user?.name ?? '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">{t('room')}:</span>
              <span>{classOccurrence.room?.name}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">{t('creditsRequired')}:</span>
              <span>{classOccurrence.class_template?.credits_required}</span>
            </div>
          </div>
        </CardContent>
        <CardFooter>
          <Button
            className="w-full"
            disabled={isFull}
            data-testid={`book-class-btn-${classOccurrence.id}`}
          >
            {isFull ? t('joinWaitlist') : t('bookNow')}
          </Button>
        </CardFooter>
      </Card>

      <ClassDetailsModal
        classOccurrence={classOccurrence}
        open={detailsOpen}
        onOpenChange={setDetailsOpen}
      />
    </>
  )
}
