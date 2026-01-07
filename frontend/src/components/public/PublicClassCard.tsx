import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { PublicClassOccurrence } from '@/types/public'
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { PublicEventDetailsModal } from './PublicEventDetailsModal'

interface PublicClassCardProps {
  classOccurrence: PublicClassOccurrence
}

export function PublicClassCard({ classOccurrence }: PublicClassCardProps) {
  const { t, i18n } = useTranslation('public')
  const [detailsOpen, setDetailsOpen] = useState(false)

  const locale = i18n.language === 'hu' ? hu : enUS
  const startTime = format(new Date(classOccurrence.starts_at), 'HH:mm', { locale })
  const endTime = format(new Date(classOccurrence.ends_at), 'HH:mm', { locale })

  const isFull = classOccurrence.is_full || classOccurrence.available_spots <= 0

  // Use template color for card background
  const colorHex = classOccurrence.class_template?.color_hex || '#3B82F6'
  const cardStyle = {
    borderLeft: `4px solid ${colorHex}`,
    backgroundColor: `${colorHex}10`, // 10% opacity
  }

  // Get trainer name from trainer.user.name
  const trainerName = classOccurrence.trainer?.user?.name || 'N/A'

  // Get room name
  const roomName = classOccurrence.room?.name || 'N/A'

  // Get site from room.site (more reliable than trainer.default_site)
  const site = classOccurrence.room?.site?.name || classOccurrence.trainer?.default_site

  return (
    <>
      <Card
        className="hover:shadow-lg transition-shadow cursor-pointer"
        onClick={() => setDetailsOpen(true)}
        style={cardStyle}
        data-testid={`class-card-${classOccurrence.id}`}
      >
        <CardHeader className="pb-2">
          <CardTitle className="text-base font-semibold leading-tight">
            {classOccurrence.class_template?.name || 'Unnamed Class'}
          </CardTitle>
          <CardDescription className="text-xs">
            {startTime} - {endTime}
          </CardDescription>
        </CardHeader>
        <CardContent className="pt-0 pb-3">
          <div className="space-y-1 text-xs">
            <div className="flex justify-between gap-1">
              <span className="text-muted-foreground">{t('common:trainer')}:</span>
              <span className="font-medium text-right">{trainerName}</span>
            </div>
            <div className="flex justify-between gap-1">
              <span className="text-muted-foreground">{t('common:room')}:</span>
              <span className="font-medium text-right">{roomName}</span>
            </div>
            {site && (
              <div className="flex justify-between gap-1">
                <span className="text-muted-foreground">{t('common:site')}:</span>
                <span className="font-medium text-right">{site}</span>
              </div>
            )}
            <div className="pt-1">
              {isFull ? (
                <Badge variant="destructive" className="text-xs">
                  {t('publicClasses.full')}
                </Badge>
              ) : (
                <Badge variant="secondary" className="text-xs">
                  {t('publicClasses.spotsAvailable', { count: classOccurrence.available_spots })}
                </Badge>
              )}
            </div>
          </div>
        </CardContent>
      </Card>

      <PublicEventDetailsModal
        classOccurrence={classOccurrence}
        open={detailsOpen}
        onOpenChange={setDetailsOpen}
      />
    </>
  )
}
