import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { adminClassOccurrencesApi } from '@/api/admin/classOccurrences'
import { classKeys } from '@/api/classes'
import { adminKeys } from '@/api/admin'
import { classPricingDefaultsApi, pricingKeys } from '@/api/pricing'
import { useToast } from '@/hooks/use-toast'
import { useAuth } from '@/hooks/useAuth'
import { ParticipantManager } from '@/components/participants/ParticipantManager'
import { AssignPricingDialog } from '@/components/pricing/AssignPricingDialog'
import type { ClassOccurrence } from '@/types/class'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { DollarSign } from 'lucide-react'

interface ClassOccurrenceDetailsModalProps {
  classOccurrence: ClassOccurrence
  open: boolean
  onOpenChange: (open: boolean) => void
  onEdit?: () => void
}

export function ClassOccurrenceDetailsModal({
  classOccurrence,
  open,
  onOpenChange,
  onEdit,
}: ClassOccurrenceDetailsModalProps) {
  const { t, i18n } = useTranslation('calendar')
  const { toast } = useToast()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [pricingDialogOpen, setPricingDialogOpen] = useState(false)

  const locale = i18n.language === 'hu' ? hu : enUS

  const isAdmin = user?.role === 'admin'
  const isStaff = user?.role === 'staff'
  // For staff ownership check, we compare staff_id with the logged-in user's id
  // The backend will enforce the actual ownership check
  const isOwner = isStaff && classOccurrence.trainer?.user?.id === user?.id
  const canManageParticipants = isAdmin || isOwner

  // Fetch active pricing for this class template
  const classTemplateId = classOccurrence.class_template?.id
  const { data: pricingOptions } = useQuery({
    queryKey: pricingKeys.classDefaultsList({ class_template_id: classTemplateId, is_active: true }),
    queryFn: () => classPricingDefaultsApi.list({ class_template_id: classTemplateId, is_active: true }),
    enabled: open && !!classTemplateId,
  })

  const activePricing = pricingOptions?.find((p) => p.is_active)

  // Format currency helper
  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('hu-HU', {
      style: 'decimal',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount) + ' Ft'
  }

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: () => adminClassOccurrencesApi.delete(classOccurrence.id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: classKeys.lists() })
      toast({ title: t('success.deleted') })
      setDeleteDialogOpen(false)
      onOpenChange(false)
    },
    onError: (error: AxiosError<ApiError>) => {
      const errorMessage = error.response?.data?.message || t('errors.deleteFailed')
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  const handleDelete = () => {
    deleteMutation.mutate()
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{classOccurrence.class_template?.title || classOccurrence.class_template?.name || t('event.eventType.GROUP_CLASS')}</DialogTitle>
            <DialogDescription>{t('classOccurrence.details')}</DialogDescription>
          </DialogHeader>

          <div className="space-y-6">
            {/* Time Information */}
            <div className="space-y-2">
              <h3 className="font-semibold">{t('event.timeInformation')}</h3>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-muted-foreground">{t('form.date')}</p>
                  <p>{format(new Date(classOccurrence.starts_at), 'PPP', { locale })}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">{t('form.startTime')} - {t('form.endTime')}</p>
                  <p>
                    {format(new Date(classOccurrence.starts_at), 'HH:mm')} -{' '}
                    {format(new Date(classOccurrence.ends_at), 'HH:mm')}
                  </p>
                </div>
              </div>
            </div>

            {/* Location Information */}
            {classOccurrence.room && (
              <div className="space-y-2">
                <h3 className="font-semibold">{t('event.locationInformation')}</h3>
                <div className="text-sm">
                  <p className="text-muted-foreground">{t('form.room')}</p>
                  <p>{classOccurrence.room.name}</p>
                </div>
              </div>
            )}

            {/* Trainer Information */}
            {classOccurrence.trainer && (
              <div className="space-y-2">
                <h3 className="font-semibold">{t('event.staffInformation')}</h3>
                <div className="text-sm">
                  <p className="text-muted-foreground">{t('event.staffName')}</p>
                  <p>{classOccurrence.trainer.user?.name}</p>
                </div>
              </div>
            )}

            {/* Capacity Information */}
            <div className="space-y-2">
              <h3 className="font-semibold">{t('classOccurrence.capacity')}</h3>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-muted-foreground">{t('classOccurrence.maxCapacity')}</p>
                  <p>{classOccurrence.capacity_override || classOccurrence.class_template?.capacity || 0}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">{t('classOccurrence.registered')}</p>
                  <p>{classOccurrence.registered_count || 0}</p>
                </div>
              </div>
            </div>

            {/* Status */}
            <div className="space-y-2">
              <h3 className="font-semibold">{t('common:status')}</h3>
              <div className="text-sm">
                <p>{t(`event.status.${classOccurrence.status}`)}</p>
              </div>
            </div>

            {/* Pricing Information */}
            {isAdmin && (
              <div className="space-y-2">
                <h3 className="font-semibold flex items-center gap-2">
                  <DollarSign className="h-4 w-4" />
                  {t('pricing.title', { ns: 'admin' })}
                </h3>
                {activePricing ? (
                  <div className="bg-green-50 p-3 rounded-md border border-green-200">
                    <p className="font-medium text-sm">
                      {activePricing.name || t('pricing.unnamedPricing', { ns: 'admin' })}
                    </p>
                    <div className="grid grid-cols-2 gap-4 mt-2 text-sm">
                      <div>
                        <p className="text-muted-foreground">{t('pricing.entryFee', { ns: 'admin' })}</p>
                        <p className="font-medium">{formatCurrency(activePricing.entry_fee_brutto)}</p>
                      </div>
                      <div>
                        <p className="text-muted-foreground">{t('pricing.trainerFee', { ns: 'admin' })}</p>
                        <p className="font-medium">{formatCurrency(activePricing.trainer_fee_brutto)}</p>
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">{t('pricing.noPricingAvailable', { ns: 'admin' })}</p>
                )}
              </div>
            )}

            {/* Participant Management */}
            {canManageParticipants && (
              <ParticipantManager
                occurrenceId={classOccurrence.id}
                isOwner={isOwner}
                capacity={classOccurrence.capacity_override || classOccurrence.class_template?.capacity || 0}
                disabled={classOccurrence.status === 'cancelled'}
              />
            )}

            {/* Notes */}
            {classOccurrence.notes && (
              <div className="space-y-2">
                <h3 className="font-semibold">{t('form.notes')}</h3>
                <p className="text-sm text-muted-foreground">{classOccurrence.notes}</p>
              </div>
            )}
          </div>

          <DialogFooter className="flex-col sm:flex-row gap-2">
            {isAdmin && (
              <>
                <Button
                  variant="outline"
                  onClick={() => setPricingDialogOpen(true)}
                >
                  <DollarSign className="h-4 w-4 mr-2" />
                  {t('pricing.assignPricing', { ns: 'admin' })}
                </Button>
                <Button
                  variant="destructive"
                  onClick={() => setDeleteDialogOpen(true)}
                  disabled={deleteMutation.isPending}
                >
                  {t('actions.delete')}
                </Button>
                {onEdit && (
                  <Button onClick={onEdit}>
                    {t('event.editEvent')}
                  </Button>
                )}
              </>
            )}
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              {t('actions.cancel')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('confirmDelete.title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('classOccurrence.deleteConfirmMessage')}
              <br />
              <strong>{t('confirmDelete.warning')}</strong>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('actions.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? t('common:loading') : t('actions.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Assign Pricing Dialog */}
      {classOccurrence.class_template && (
        <AssignPricingDialog
          open={pricingDialogOpen}
          onOpenChange={setPricingDialogOpen}
          classTemplateId={classOccurrence.class_template.id}
          classTemplateName={classOccurrence.class_template.title || classOccurrence.class_template.name}
          onSuccess={() => {
            queryClient.invalidateQueries({ queryKey: classKeys.lists() })
            queryClient.invalidateQueries({ queryKey: adminKeys.classTemplates() })
            queryClient.invalidateQueries({ queryKey: pricingKeys.classDefaults() })
          }}
        />
      )}
    </>
  )
}
