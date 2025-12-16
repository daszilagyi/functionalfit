import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { PublicClassOccurrence } from '@/types/public'
import { classesApi, classKeys } from '@/api/classes'
import { publicKeys } from '@/api/public'
import { useAuth } from '@/hooks/useAuth'
import { UserRole } from '@/types/user'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { useToast } from '@/hooks/use-toast'
import { QuickRegisterModal } from './QuickRegisterModal'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

interface PublicEventDetailsModalProps {
  classOccurrence: PublicClassOccurrence
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function PublicEventDetailsModal({
  classOccurrence,
  open,
  onOpenChange,
}: PublicEventDetailsModalProps) {
  const { t, i18n } = useTranslation('public')
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const { isAuthenticated, user } = useAuth()
  const [showQuickRegister, setShowQuickRegister] = useState(false)

  // Check if user can book (must be a client with client profile)
  const isClient = user?.role === UserRole.CLIENT
  const hasClientProfile = !!user?.client?.id
  const canBook = !isAuthenticated || (isClient && hasClientProfile)

  const locale = i18n.language === 'hu' ? hu : enUS
  const startTime = format(new Date(classOccurrence.starts_at), 'HH:mm', { locale })
  const endTime = format(new Date(classOccurrence.ends_at), 'HH:mm', { locale })
  const dateTime = format(new Date(classOccurrence.starts_at), 'PPPp', { locale })

  const isFull = classOccurrence.is_full || classOccurrence.available_spots <= 0

  // Get data from actual backend response structure
  const templateName = classOccurrence.class_template?.name || 'Unnamed Class'
  const templateDescription = classOccurrence.class_template?.description || ''
  const trainerName = classOccurrence.trainer?.user?.name || 'N/A'
  const roomName = classOccurrence.room?.name || 'N/A'
  const site = classOccurrence.trainer?.default_site

  const bookMutation = useMutation({
    mutationFn: () => classesApi.book(String(classOccurrence.id), {}),
    onSuccess: (response) => {
      // Invalidate both public and authenticated class queries
      queryClient.invalidateQueries({ queryKey: publicKeys.classes() })
      queryClient.invalidateQueries({ queryKey: classKeys.lists() })

      const message =
        response.status === 'booked'
          ? t('publicClasses.booking.successConfirmed')
          : t('publicClasses.booking.successWaitlist', {
              position: classOccurrence.booked_count + 1,
            })

      toast({
        title: t('common:success'),
        description: message,
      })

      onOpenChange(false)
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}

      let errorMessage = t('errors.bookFailed')

      if (status === 409) {
        errorMessage = t('publicClasses.booking.alreadyBooked')
      } else if (status === 422 && data?.message) {
        errorMessage = data.message
      } else if (status === 423) {
        errorMessage = t('publicClasses.booking.deadlinePassed')
      } else if (status === 451) {
        // Policy violation (e.g., no active pass)
        errorMessage = data?.message ?? t('publicClasses.booking.noActivePass')
      }

      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: errorMessage,
      })
    },
  })

  const handleBook = () => {
    if (!isAuthenticated) {
      // Close the details modal first to prevent z-index/pointer event conflicts
      onOpenChange(false)
      // Show quick registration modal
      setShowQuickRegister(true)
      return
    }

    bookMutation.mutate()
  }

  const handleQuickRegisterSuccess = () => {
    // After successful registration, trigger booking
    setShowQuickRegister(false)
    // Wait a bit for auth state to update, then book
    setTimeout(() => {
      bookMutation.mutate()
    }, 500)
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-2xl" data-testid="event-details-modal">
          <DialogHeader>
            <div className="flex justify-between items-start gap-4">
              <DialogTitle className="text-2xl">
                {templateName}
              </DialogTitle>
              {isFull ? (
                <Badge variant="destructive">
                  {t('publicClasses.full')}
                </Badge>
              ) : (
                <Badge variant="secondary">
                  {t('publicClasses.spotsAvailable', {
                    count: classOccurrence.available_spots,
                  })}
                </Badge>
              )}
            </div>
            <DialogDescription className="text-base">
              {templateDescription}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            {/* Class details display */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label className="text-muted-foreground">
                  {t('common:dateTime')}
                </Label>
                <div className="font-medium">{dateTime}</div>
              </div>
              <div>
                <Label className="text-muted-foreground">
                  {t('common:duration')}
                </Label>
                <div className="font-medium">
                  {startTime} - {endTime}
                </div>
              </div>
              <div>
                <Label className="text-muted-foreground">
                  {t('common:trainer')}
                </Label>
                <div className="font-medium">{trainerName}</div>
              </div>
              <div>
                <Label className="text-muted-foreground">
                  {t('common:room')}
                </Label>
                <div className="font-medium">
                  {roomName}{site ? ` (${site})` : ''}
                </div>
              </div>
              <div>
                <Label className="text-muted-foreground">
                  {t('publicClasses.capacity')}
                </Label>
                <div className="font-medium">
                  {classOccurrence.booked_count} / {classOccurrence.capacity}
                </div>
              </div>
            </div>

            {/* Status message for unauthenticated users */}
            {!isAuthenticated && (
              <div className="bg-muted p-4 rounded-lg border">
                <p className="text-sm text-muted-foreground">
                  {t('publicClasses.registerToBook')}
                </p>
              </div>
            )}

            {/* Status message for admin/staff users without client profile */}
            {isAuthenticated && !canBook && (
              <div className="bg-amber-50 dark:bg-amber-950 p-4 rounded-lg border border-amber-200 dark:border-amber-800">
                <p className="text-sm text-amber-700 dark:text-amber-300">
                  {t('publicClasses.booking.noClientProfile')}
                </p>
              </div>
            )}
          </div>

          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={bookMutation.isPending}
            >
              {t('common:close')}
            </Button>
            <Button
              onClick={handleBook}
              disabled={bookMutation.isPending || (isAuthenticated && !canBook)}
              data-testid="book-button"
            >
              {bookMutation.isPending ? (
                t('common:loading')
              ) : isAuthenticated ? (
                isFull ? (
                  t('publicClasses.joinWaitlist')
                ) : (
                  t('publicClasses.book')
                )
              ) : (
                t('publicClasses.registerAndBook')
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <QuickRegisterModal
        open={showQuickRegister}
        onOpenChange={setShowQuickRegister}
        onSuccess={handleQuickRegisterSuccess}
      />
    </>
  )
}
