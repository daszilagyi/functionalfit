import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { format } from 'date-fns'
import { hu } from 'date-fns/locale'
import { ClassOccurrence } from '@/types/class'
import { classesApi, classKeys } from '@/api/classes'
import { bookClassSchema, BookClassFormData } from '@/lib/validations/booking'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { useToast } from '@/hooks/use-toast'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

interface ClassDetailsModalProps {
  classOccurrence: ClassOccurrence
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ClassDetailsModal({ classOccurrence, open, onOpenChange }: ClassDetailsModalProps) {
  const { t, i18n } = useTranslation('classes')
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [showBookingForm, setShowBookingForm] = useState(false)

  const form = useForm<BookClassFormData>({
    resolver: zodResolver(bookClassSchema),
    defaultValues: { notes: '' },
  })

  const bookMutation = useMutation({
    mutationFn: (data: BookClassFormData) => classesApi.book(classOccurrence.id, data),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: classKeys.lists() })
      queryClient.invalidateQueries({ queryKey: classKeys.detail(classOccurrence.id) })

      const message = (response as { status?: string }).status === 'confirmed'
        ? t('booking.successConfirmed')
        : t('booking.successWaitlist')

      toast({
        title: t('common:success'),
        description: message,
      })

      onOpenChange(false)
      setShowBookingForm(false)
      form.reset()
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}

      let errorMessage = t('errors.bookFailed')

      if (status === 409) {
        errorMessage = t('errors.conflict')
      } else if (status === 422 && data?.message) {
        errorMessage = data.message
      } else if (status === 423) {
        // Locked time window error
        errorMessage = data?.message ?? t('errors.bookFailed')
      }

      toast({
        variant: 'destructive',
        title: t('common:error'),
        description: errorMessage,
      })
    },
  })

  const handleBook = form.handleSubmit((data) => {
    bookMutation.mutate(data)
  })

  const locale = i18n.language === 'hu' ? hu : undefined
  const startTime = format(new Date(classOccurrence.starts_at), 'HH:mm', { locale })
  const endTime = format(new Date(classOccurrence.ends_at), 'HH:mm', { locale })
  const date = format(new Date(classOccurrence.starts_at), 'PPP', { locale })

  const capacity = classOccurrence.capacity_override ?? classOccurrence.class_template?.capacity ?? 0
  const registeredCount = classOccurrence.registered_count ?? 0
  const spotsLeft = capacity - registeredCount

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl" data-testid="class-details-modal">
        <DialogHeader>
          <DialogTitle>{classOccurrence.class_template?.title}</DialogTitle>
          <DialogDescription>
            {classOccurrence.class_template?.description}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Class details display */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label className="text-muted-foreground">{t('common:date')}</Label>
              <div>{date}</div>
            </div>
            <div>
              <Label className="text-muted-foreground">{t('common:time')}</Label>
              <div>{startTime} - {endTime}</div>
            </div>
            <div>
              <Label className="text-muted-foreground">{t('trainer')}</Label>
              <div>{classOccurrence.trainer?.user?.name ?? '-'}</div>
            </div>
            <div>
              <Label className="text-muted-foreground">{t('room')}</Label>
              <div>{classOccurrence.room?.name}</div>
            </div>
            <div>
              <Label className="text-muted-foreground">{t('capacity')}</Label>
              <div>{registeredCount} / {capacity}</div>
            </div>
            <div>
              <Label className="text-muted-foreground">{t('creditsRequired')}</Label>
              <div>{classOccurrence.class_template?.credits_required}</div>
            </div>
          </div>

          {/* Booking form */}
          {showBookingForm && (
            <form onSubmit={handleBook} className="space-y-4 border-t pt-4">
              <div>
                <Label htmlFor="notes">{t('booking.notesPlaceholder')}</Label>
                <Input
                  id="notes"
                  {...form.register('notes')}
                  placeholder={t('booking.notesPlaceholder')}
                  data-testid="booking-notes-input"
                />
                {form.formState.errors.notes && (
                  <p className="text-sm text-destructive mt-1">
                    {t(form.formState.errors.notes.message ?? 'errors.bookFailed')}
                  </p>
                )}
              </div>
            </form>
          )}
        </div>

        <DialogFooter>
          {!showBookingForm ? (
            <>
              <Button variant="outline" onClick={() => onOpenChange(false)}>
                {t('common:close')}
              </Button>
              <Button
                onClick={() => setShowBookingForm(true)}
                data-testid="show-booking-form-btn"
              >
                {spotsLeft > 0 ? t('bookNow') : t('joinWaitlist')}
              </Button>
            </>
          ) : (
            <>
              <Button
                variant="outline"
                onClick={() => {
                  setShowBookingForm(false)
                  form.reset()
                }}
                disabled={bookMutation.isPending}
              >
                {t('common:cancel')}
              </Button>
              <Button
                onClick={handleBook}
                disabled={bookMutation.isPending}
                data-testid="submit-booking-btn"
              >
                {bookMutation.isPending ? t('common:loading') : t('common:submit')}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
