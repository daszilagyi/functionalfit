import { useTranslation } from 'react-i18next'
import { useAuth } from '@/hooks/useAuth'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { User, Users } from 'lucide-react'

interface EventTypeSelectorModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSelectIndividual: () => void
  onSelectGroupClass: () => void
}

export function EventTypeSelectorModal({
  open,
  onOpenChange,
  onSelectIndividual,
  onSelectGroupClass,
}: EventTypeSelectorModalProps) {
  const { t } = useTranslation('calendar')
  const { user } = useAuth()

  const isAdmin = user?.role === 'admin'

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('eventTypeSelector.title')}</DialogTitle>
          <DialogDescription>{t('eventTypeSelector.description')}</DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 py-4">
          <Button
            variant="outline"
            className="h-auto p-4 justify-start"
            onClick={() => {
              onOpenChange(false)
              onSelectIndividual()
            }}
          >
            <User className="h-8 w-8 mr-4 text-blue-500" />
            <div className="text-left">
              <div className="font-semibold">{t('eventTypeSelector.individual')}</div>
              <div className="text-sm text-muted-foreground">
                {t('eventTypeSelector.individualDescription')}
              </div>
            </div>
          </Button>

          {isAdmin && (
            <Button
              variant="outline"
              className="h-auto p-4 justify-start"
              onClick={() => {
                onOpenChange(false)
                onSelectGroupClass()
              }}
            >
              <Users className="h-8 w-8 mr-4 text-green-500" />
              <div className="text-left">
                <div className="font-semibold">{t('eventTypeSelector.groupClass')}</div>
                <div className="text-sm text-muted-foreground">
                  {t('eventTypeSelector.groupClassDescription')}
                </div>
              </div>
            </Button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
