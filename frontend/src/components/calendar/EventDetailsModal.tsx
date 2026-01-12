import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { format, isPast } from 'date-fns'
import { hu } from 'date-fns/locale'
import { eventsApi, eventKeys } from '@/api/events'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { useToast } from '@/hooks/use-toast'
import { CheckCircle2, XCircle, Wrench } from 'lucide-react'
import type { Event } from '@/types/event'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

interface EventDetailsModalProps {
  event: Event
  open: boolean
  onOpenChange: (open: boolean) => void
  onEventUpdated?: () => void
  onEdit?: (event: Event) => void
  isAdmin?: boolean
  canEdit?: boolean // true if user can edit this event (admin or owner)
}

export function EventDetailsModal({ event, open, onOpenChange, onEventUpdated, onEdit, isAdmin, canEdit }: EventDetailsModalProps) {
  const { t, i18n } = useTranslation('calendar')
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [checkInNotes, setCheckInNotes] = useState('')

  const locale = i18n.language === 'hu' ? hu : undefined

  // Get additional clients list (support both snake_case and camelCase)
  const additionalClientsList = event.additional_clients || event.additionalClients || []

  // Check if event start time is in the past (staff cannot delete past events, admin can)
  const eventStartsInPast = isPast(new Date(event.starts_at))
  const canDeleteEvent = isAdmin || !eventStartsInPast

  // Check if event is past and can be checked in (for any guest)
  const eventIsPastAndIndividual = event.status === 'scheduled' && isPast(new Date(event.ends_at)) && event.type === 'INDIVIDUAL'

  // Show check-in section for all past INDIVIDUAL events with clients
  // Allow changing attendance status even after initial check-in
  const canCheckIn = eventIsPastAndIndividual && (event.client || additionalClientsList.length > 0)

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
    mutationFn: () => eventsApi.delete(event.id),
    onSuccess: async () => {
      // Refetch all event queries immediately
      await queryClient.refetchQueries({
        queryKey: eventKeys.all,
        type: 'active'
      })
      toast({ title: t('success.deleted') })
      onOpenChange(false)
      setDeleteDialogOpen(false)
      onEventUpdated?.()
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}
      let errorMessage = t('errors.deleteFailed')
      if (status === 403) errorMessage = t('errors.forbidden')
      else if (status === 422 && data?.message) errorMessage = data.message
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Check-in mutation - supports per-guest check-in with optional client_id and guest_index
  const checkInMutation = useMutation({
    mutationFn: ({ attended, clientId, guestIndex }: { attended: boolean; clientId?: number; guestIndex?: number }) =>
      eventsApi.checkIn(event.id, {
        attendance_status: attended ? 'attended' : 'no_show',
        notes: checkInNotes || undefined,
        client_id: clientId,
        guest_index: guestIndex
      }),
    onSuccess: async (response) => {
      // Refetch all event queries immediately
      await queryClient.refetchQueries({
        queryKey: eventKeys.all,
        type: 'active'
      })
      toast({
        title: t('success.checkedIn'),
        description: response.pass_credit_deducted
          ? t('success.passCreditDeducted')
          : undefined,
      })
      setCheckInNotes('')
      onEventUpdated?.()
      // Don't close modal for multi-guest - user might want to check in more guests
      const hasMultipleGuests = (event.additional_clients || event.additionalClients || []).length > 0
      if (!hasMultipleGuests) {
        onOpenChange(false)
      }
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}
      let errorMessage = t('errors.checkInFailed')
      if (status === 403) errorMessage = t('errors.forbidden')
      else if (status === 422 && data?.message) errorMessage = data.message
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Format dates
  const formatDate = (dateString: string) => {
    return format(new Date(dateString), 'MMM d, yyyy', { locale })
  }

  const formatTime = (dateString: string) => {
    return format(new Date(dateString), 'HH:mm', { locale })
  }

  const formatDateTime = (dateString: string) => {
    return format(new Date(dateString), 'MMM d, yyyy HH:mm', { locale })
  }

  // Get status badge variant
  const getStatusBadgeVariant = (status: Event['status']): 'default' | 'secondary' | 'destructive' => {
    switch (status) {
      case 'scheduled':
        return 'default'
      case 'completed':
        return 'secondary'
      case 'cancelled':
        return 'destructive'
      default:
        return 'default'
    }
  }

  // Get type badge variant
  const getTypeBadgeVariant = (type: Event['type']): 'default' | 'secondary' | 'outline' => {
    switch (type) {
      case 'INDIVIDUAL':
        return 'default'
      case 'GROUP_CLASS':
        return 'secondary'
      case 'BLOCK':
        return 'outline'
      default:
        return 'default'
    }
  }

  const handleDelete = () => {
    setDeleteDialogOpen(true)
  }

  const confirmDelete = () => {
    deleteMutation.mutate()
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <div className="flex items-center justify-between">
              <DialogTitle>{t('event.eventDetails')}</DialogTitle>
              <div className="flex gap-2">
                <Badge variant={getTypeBadgeVariant(event.type)}>
                  {t(`event.eventType.${event.type}`)}
                </Badge>
                <Badge variant={getStatusBadgeVariant(event.status)}>
                  {t(`event.status.${event.status}`)}
                </Badge>
              </div>
            </div>
          </DialogHeader>

          <div className="space-y-4">
            {/* Participants Information (for INDIVIDUAL events) */}
            {event.type === 'INDIVIDUAL' && (event.client || (event.additional_clients && event.additional_clients.length > 0) || (event.additionalClients && event.additionalClients.length > 0)) && (
              <div className="space-y-2">
                <Label className="text-lg font-semibold">{t('event.participants')}</Label>
                <div className="pl-4 space-y-3">
                  {/* Main client first */}
                  {event.client && (() => {
                    const client = event.client
                    const clientName = client.user?.name || client.full_name || `Client #${client.id}`
                    const clientEmail = client.user?.email
                    const clientPhone = client.user?.phone
                    const isTechnicalGuest = client.is_technical_guest
                    // Main client pricing from event level
                    const hasMainPricing = event.entry_fee_brutto !== null || event.trainer_fee_brutto !== null
                    return (
                      <div key={`main-${client.id}`} className="p-3 border rounded-md space-y-2">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <span className="font-medium">{clientName}</span>
                            <Badge variant="outline" className="text-xs">
                              {t('event.mainGuest')}
                            </Badge>
                            {isTechnicalGuest && (
                              <Badge variant="secondary" className="text-xs">
                                <Wrench className="h-3 w-3 mr-1" />
                                {t('event.technicalGuest')}
                              </Badge>
                            )}
                          </div>
                        </div>
                        {clientEmail && (
                          <div className="text-sm text-muted-foreground">
                            <Label className="text-muted-foreground text-xs">{t('event.clientEmail')}</Label>
                            <div>{clientEmail}</div>
                          </div>
                        )}
                        {clientPhone && (
                          <div className="text-sm text-muted-foreground">
                            <Label className="text-muted-foreground text-xs">{t('event.clientPhone')}</Label>
                            <div>{clientPhone}</div>
                          </div>
                        )}
                        {/* Show pricing for main client */}
                        {hasMainPricing && (
                          <div className="mt-2 pt-2 border-t text-sm">
                            <div className="text-muted-foreground mb-1">
                              <span className="font-medium">{t('form.pricing')}</span>
                            </div>
                            <div className="grid grid-cols-2 gap-2 text-xs">
                              <div>
                                <span className="text-muted-foreground">{t('form.entryFee')}: </span>
                                <span className="font-medium">{formatCurrency(event.entry_fee_brutto ?? 0)}</span>
                              </div>
                              <div>
                                <span className="text-muted-foreground">{t('form.trainerFee')}: </span>
                                <span className="font-medium">{formatCurrency(event.trainer_fee_brutto ?? 0)}</span>
                              </div>
                            </div>
                            {event.price_source && (
                              <div className="text-xs text-muted-foreground mt-1">
                                {event.price_source === 'client_price_code'
                                  ? t('form.pricingSourceClient')
                                  : t('form.pricingSourceDefault')}
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    )
                  })()}
                  {/* Additional clients with their pricing */}
                  {(() => {
                    const additionalClientsList = event.additional_clients || event.additionalClients || []

                    return additionalClientsList.map((client, index) => {
                      if (!client) return null
                      const clientName = client.user?.name || client.full_name || `Client #${client.id}`
                      const clientEmail = client.user?.email
                      const clientPhone = client.user?.phone
                      const isTechnicalGuest = client.is_technical_guest
                      const quantity = client.pivot?.quantity ?? 1
                      // Get pricing from pivot
                      const pivotPricing = client.pivot
                      const hasPricing = pivotPricing?.entry_fee_brutto !== null || pivotPricing?.trainer_fee_brutto !== null

                      // If quantity > 1, show a summary for all
                      return (
                        <div key={`${client.id}-${index}`} className="p-3 border rounded-md space-y-2">
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                              <span className="font-medium">
                                {clientName}
                                {quantity > 1 && <span className="text-muted-foreground ml-1">×{quantity}</span>}
                              </span>
                              {isTechnicalGuest && (
                                <Badge variant="secondary" className="text-xs">
                                  <Wrench className="h-3 w-3 mr-1" />
                                  {t('event.technicalGuest')}
                                </Badge>
                              )}
                            </div>
                          </div>
                          {clientEmail && (
                            <div className="text-sm text-muted-foreground">
                              <Label className="text-muted-foreground text-xs">{t('event.clientEmail')}</Label>
                              <div>{clientEmail}</div>
                            </div>
                          )}
                          {clientPhone && (
                            <div className="text-sm text-muted-foreground">
                              <Label className="text-muted-foreground text-xs">{t('event.clientPhone')}</Label>
                              <div>{clientPhone}</div>
                            </div>
                          )}
                          {/* Show pricing for this additional client */}
                          {hasPricing && (
                            <div className="mt-2 pt-2 border-t text-sm">
                              <div className="text-muted-foreground mb-1">
                                <span className="font-medium">{t('form.pricing')}</span>
                                {quantity > 1 && (
                                  <span className="text-xs ml-1">({t('form.perGuest')})</span>
                                )}
                              </div>
                              <div className="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                  <span className="text-muted-foreground">{t('form.entryFee')}: </span>
                                  <span className="font-medium">{formatCurrency(pivotPricing?.entry_fee_brutto ?? 0)}</span>
                                </div>
                                <div>
                                  <span className="text-muted-foreground">{t('form.trainerFee')}: </span>
                                  <span className="font-medium">{formatCurrency(pivotPricing?.trainer_fee_brutto ?? 0)}</span>
                                </div>
                              </div>
                              {pivotPricing?.price_source && (
                                <div className="text-xs text-muted-foreground mt-1">
                                  {pivotPricing.price_source === 'client_price_code'
                                    ? t('form.pricingSourceClient')
                                    : t('form.pricingSourceDefault')}
                                </div>
                              )}
                              {quantity > 1 && (
                                <div className="text-xs text-muted-foreground mt-1 font-medium">
                                  {t('form.subtotal')}: {formatCurrency((pivotPricing?.entry_fee_brutto ?? 0) * quantity)} + {formatCurrency((pivotPricing?.trainer_fee_brutto ?? 0) * quantity)}
                                </div>
                              )}
                            </div>
                          )}
                        </div>
                      )
                    })
                  })()}
                </div>
              </div>
            )}

            <Separator />

            {/* Time Information */}
            <div className="space-y-2">
              <Label className="text-lg font-semibold">{t('event.timeInformation')}</Label>
              <div className="grid grid-cols-2 gap-4 pl-4">
                <div>
                  <Label className="text-muted-foreground">{t('form.date')}</Label>
                  <div className="font-medium">{formatDate(event.starts_at)}</div>
                </div>
                <div>
                  <Label className="text-muted-foreground">{t('form.duration')}</Label>
                  <div className="font-medium">{event.duration_minutes} {t('common.minutes')}</div>
                </div>
                <div>
                  <Label className="text-muted-foreground">{t('form.startTime')}</Label>
                  <div className="font-medium">{formatTime(event.starts_at)}</div>
                </div>
                <div>
                  <Label className="text-muted-foreground">{t('form.endTime')}</Label>
                  <div className="font-medium">{formatTime(event.ends_at)}</div>
                </div>
              </div>
            </div>

            <Separator />

            {/* Location Information */}
            {event.room && (
              <>
                <div className="space-y-2">
                  <Label className="text-lg font-semibold">{t('event.locationInformation')}</Label>
                  <div className="grid grid-cols-2 gap-4 pl-4">
                    <div>
                      <Label className="text-muted-foreground">{t('form.room')}</Label>
                      <div className="font-medium">{event.room.name}</div>
                    </div>
                    <div>
                      <Label className="text-muted-foreground">{t('event.facility')}</Label>
                      <div className="font-medium">{event.room.facility}</div>
                    </div>
                    {event.room.location && (
                      <div className="col-span-2">
                        <Label className="text-muted-foreground">{t('event.location')}</Label>
                        <div className="font-medium">{event.room.location}</div>
                      </div>
                    )}
                  </div>
                </div>
                <Separator />
              </>
            )}

            {/* Staff Information */}
            {event.staff && (
              <>
                <div className="space-y-2">
                  <Label className="text-lg font-semibold">{t('event.staffInformation')}</Label>
                  <div className="grid grid-cols-2 gap-4 pl-4">
                    <div>
                      <Label className="text-muted-foreground">{t('event.staffName')}</Label>
                      <div className="font-medium">{event.staff.user.name}</div>
                    </div>
                    <div>
                      <Label className="text-muted-foreground">{t('event.staffEmail')}</Label>
                      <div className="font-medium">{event.staff.user.email}</div>
                    </div>
                  </div>
                </div>
                <Separator />
              </>
            )}

            {/* Notes */}
            {event.notes && (
              <>
                <div className="space-y-2">
                  <Label className="text-lg font-semibold">{t('form.notes')}</Label>
                  <div className="pl-4 text-sm">{event.notes}</div>
                </div>
                <Separator />
              </>
            )}

            {/* Pricing Summary - Total for all guests */}
            {event.type === 'INDIVIDUAL' && (() => {
              // Calculate totals from all clients' pricing
              let totalEntryFee = 0
              let totalTrainerFee = 0
              let hasPricing = false

              // Main client pricing
              if (event.entry_fee_brutto !== null && event.entry_fee_brutto !== undefined) {
                totalEntryFee += event.entry_fee_brutto
                hasPricing = true
              }
              if (event.trainer_fee_brutto !== null && event.trainer_fee_brutto !== undefined) {
                totalTrainerFee += event.trainer_fee_brutto
              }

              // Additional clients pricing
              const additionalClients = event.additional_clients || event.additionalClients || []
              for (const client of additionalClients) {
                const quantity = client.pivot?.quantity ?? 1
                if (client.pivot?.entry_fee_brutto !== null && client.pivot?.entry_fee_brutto !== undefined) {
                  totalEntryFee += client.pivot.entry_fee_brutto * quantity
                  hasPricing = true
                }
                if (client.pivot?.trainer_fee_brutto !== null && client.pivot?.trainer_fee_brutto !== undefined) {
                  totalTrainerFee += client.pivot.trainer_fee_brutto * quantity
                }
              }

              if (!hasPricing) return null

              const grandTotal = totalEntryFee + totalTrainerFee

              return (
                <>
                  <div className="space-y-2">
                    <Label className="text-lg font-semibold">{t('form.pricing')}</Label>
                    <div className="pl-4">
                      <div className="bg-blue-50 p-3 rounded-md border border-blue-200">
                        <div className="grid grid-cols-2 gap-4 text-sm">
                          <div>
                            <p className="text-muted-foreground">{t('form.entryFee')} ({t('form.subtotal')})</p>
                            <p className="font-medium text-lg">{formatCurrency(totalEntryFee)}</p>
                          </div>
                          <div>
                            <p className="text-muted-foreground">{t('form.trainerFee')} ({t('form.subtotal')})</p>
                            <p className="font-medium text-lg">{formatCurrency(totalTrainerFee)}</p>
                          </div>
                        </div>
                        <div className="mt-3 pt-3 border-t border-blue-300">
                          <div className="flex justify-between items-center">
                            <span className="font-semibold">{t('form.subtotal')}:</span>
                            <span className="font-bold text-xl">{formatCurrency(grandTotal)}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <Separator />
                </>
              )
            })()}

            {/* Check-in Section (for past events that haven't been checked in) */}
            {canCheckIn && (
              <>
                <div className="space-y-4">
                  <Label className="text-lg font-semibold">{t('event.checkIn')}</Label>
                  <div className="pl-4 space-y-4">
                    {/* Notes field */}
                    <div className="space-y-2">
                      <Label htmlFor="checkin-notes">{t('event.checkInNotes')}</Label>
                      <Textarea
                        id="checkin-notes"
                        placeholder={t('event.checkInNotesPlaceholder')}
                        value={checkInNotes}
                        onChange={(e) => setCheckInNotes(e.target.value)}
                        rows={2}
                      />
                    </div>

                    {/* Per-guest check-in buttons */}
                    {(() => {
                      // Build list of ALL clients for check-in (including those already checked in)
                      // Each additional client now has its own record with a unique guest_index
                      const clientsToCheckIn: Array<{
                        id: string
                        name: string
                        isMain: boolean
                        isTechnicalGuest: boolean
                        attendanceStatus: string | null | undefined
                        guestIndex?: number
                      }> = []

                      // Main client - always include if exists
                      if (event.client) {
                        const clientName = event.client.user?.name || event.client.full_name || `Client #${event.client.id}`
                        clientsToCheckIn.push({
                          id: event.client.id,
                          name: clientName,
                          isMain: true,
                          isTechnicalGuest: event.client.is_technical_guest || false,
                          attendanceStatus: event.attendance_status ?? undefined
                        })
                      }

                      // Additional clients - each record is now a separate guest
                      // Count how many of each client_id we have for display purposes
                      const clientCounts = new Map<string, number>()
                      for (const client of additionalClientsList) {
                        const count = clientCounts.get(client.id) || 0
                        clientCounts.set(client.id, count + 1)
                      }

                      // Track current index for each client_id for display
                      const clientCurrentIndex = new Map<string, number>()
                      for (const client of additionalClientsList) {
                        const clientName = client.user?.name || client.full_name || `Client #${client.id}`
                        const guestIndex = client.pivot?.guest_index ?? 0
                        const totalCount = clientCounts.get(client.id) || 1
                        const currentIdx = (clientCurrentIndex.get(client.id) || 0) + 1
                        clientCurrentIndex.set(client.id, currentIdx)

                        clientsToCheckIn.push({
                          id: client.id,
                          name: totalCount > 1 ? `${clientName} (${currentIdx}/${totalCount})` : clientName,
                          isMain: false,
                          isTechnicalGuest: client.is_technical_guest || false,
                          attendanceStatus: client.pivot?.attendance_status ?? undefined,
                          guestIndex: guestIndex
                        })
                      }

                      // If only one client, show simple buttons
                      if (clientsToCheckIn.length === 1) {
                        const client = clientsToCheckIn[0]
                        const isAttended = client.attendanceStatus === 'attended'
                        const isNoShow = client.attendanceStatus === 'no_show'
                        return (
                          <div className="space-y-2">
                            {client.attendanceStatus && (
                              <div className="text-sm text-muted-foreground mb-2">
                                {t('event.attendanceStatus')}: {isAttended ? (
                                  <Badge className="bg-green-500 hover:bg-green-600 ml-1">{t('event.attended')}</Badge>
                                ) : (
                                  <Badge variant="destructive" className="ml-1">{t('event.noShow')}</Badge>
                                )}
                              </div>
                            )}
                            <div className="flex gap-4">
                              <Button
                                onClick={() => checkInMutation.mutate({
                                  attended: true,
                                  clientId: parseInt(client.id),
                                  guestIndex: client.guestIndex
                                })}
                                disabled={checkInMutation.isPending || isAttended}
                                className={`flex-1 ${isAttended ? 'bg-green-700 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700'}`}
                              >
                                <CheckCircle2 className="h-4 w-4 mr-2" />
                                {t('event.markAttended')}
                              </Button>
                              <Button
                                onClick={() => checkInMutation.mutate({
                                  attended: false,
                                  clientId: parseInt(client.id),
                                  guestIndex: client.guestIndex
                                })}
                                disabled={checkInMutation.isPending || isNoShow}
                                variant="destructive"
                                className={`flex-1 ${isNoShow ? 'opacity-70 cursor-not-allowed' : ''}`}
                              >
                                <XCircle className="h-4 w-4 mr-2" />
                                {t('event.markNoShow')}
                              </Button>
                            </div>
                          </div>
                        )
                      }

                      // Multiple clients - show per-guest buttons
                      return (
                        <div className="space-y-3">
                          {clientsToCheckIn.map((client, index) => {
                            const isAttended = client.attendanceStatus === 'attended'
                            const isNoShow = client.attendanceStatus === 'no_show'
                            return (
                              <div key={`${client.id}-${client.guestIndex ?? index}`} className="p-3 border rounded-md space-y-2">
                                <div className="flex items-center justify-between">
                                  <div className="flex items-center gap-2">
                                    <span className="font-medium text-sm">{client.name}</span>
                                    {client.isMain && (
                                      <Badge variant="outline" className="text-xs">
                                        {t('event.mainGuest')}
                                      </Badge>
                                    )}
                                    {client.isTechnicalGuest && (
                                      <Badge variant="secondary" className="text-xs">
                                        <Wrench className="h-3 w-3 mr-1" />
                                        {t('event.technicalGuest')}
                                      </Badge>
                                    )}
                                  </div>
                                  {client.attendanceStatus && (
                                    <div>
                                      {isAttended ? (
                                        <Badge className="bg-green-500 hover:bg-green-600">{t('event.attended')}</Badge>
                                      ) : (
                                        <Badge variant="destructive">{t('event.noShow')}</Badge>
                                      )}
                                    </div>
                                  )}
                                </div>
                                <div className="flex gap-2">
                                  <Button
                                    size="sm"
                                    onClick={() => checkInMutation.mutate({
                                      attended: true,
                                      clientId: parseInt(client.id),
                                      guestIndex: client.guestIndex
                                    })}
                                    disabled={checkInMutation.isPending || isAttended}
                                    className={`flex-1 ${isAttended ? 'bg-green-700 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700'}`}
                                  >
                                    <CheckCircle2 className="h-3 w-3 mr-1" />
                                    {t('event.markAttended')}
                                  </Button>
                                  <Button
                                    size="sm"
                                    onClick={() => checkInMutation.mutate({
                                      attended: false,
                                      clientId: parseInt(client.id),
                                      guestIndex: client.guestIndex
                                    })}
                                    disabled={checkInMutation.isPending || isNoShow}
                                    variant="destructive"
                                    className={`flex-1 ${isNoShow ? 'opacity-70 cursor-not-allowed' : ''}`}
                                  >
                                    <XCircle className="h-3 w-3 mr-1" />
                                    {t('event.markNoShow')}
                                  </Button>
                                </div>
                              </div>
                            )
                          })}
                        </div>
                      )
                    })()}
                  </div>
                </div>
                <Separator />
              </>
            )}

            {/* Attendance Information (show when any attendance_status is set) */}
            {(() => {
              // Check if any client has attendance status
              const hasAnyAttendance = event.attendance_status || additionalClientsList.some(c => c.pivot?.attendance_status)
              if (!hasAnyAttendance) return null

              // Build attendance records
              const attendanceRecords: Array<{
                name: string
                isMain: boolean
                isTechnicalGuest: boolean
                status: string
                checkedInAt?: string
              }> = []

              // Main client attendance
              if (event.attendance_status && event.client) {
                const clientName = event.client.user?.name || event.client.full_name || `Client #${event.client.id}`
                attendanceRecords.push({
                  name: clientName,
                  isMain: true,
                  isTechnicalGuest: event.client.is_technical_guest || false,
                  status: event.attendance_status,
                  checkedInAt: event.checked_in_at ?? undefined
                })
              }

              // Additional clients attendance
              for (const client of additionalClientsList) {
                if (client.pivot?.attendance_status) {
                  const clientName = client.user?.name || client.full_name || `Client #${client.id}`
                  const quantity = client.pivot?.quantity ?? 1
                  for (let i = 0; i < quantity; i++) {
                    attendanceRecords.push({
                      name: clientName + (quantity > 1 ? ` (${i + 1}/${quantity})` : ''),
                      isMain: false,
                      isTechnicalGuest: client.is_technical_guest || false,
                      status: client.pivot.attendance_status,
                      checkedInAt: client.pivot.checked_in_at ?? undefined
                    })
                  }
                }
              }

              if (attendanceRecords.length === 0) return null

              return (
                <>
                  <div className="space-y-2">
                    <Label className="text-lg font-semibold">{t('event.attendanceInformation')}</Label>
                    <div className="pl-4 space-y-2">
                      {attendanceRecords.map((record, index) => (
                        <div key={index} className="flex items-center justify-between p-2 border rounded-md">
                          <div className="flex items-center gap-2">
                            <span className="font-medium text-sm">{record.name}</span>
                            {record.isMain && (
                              <Badge variant="outline" className="text-xs">
                                {t('event.mainGuest')}
                              </Badge>
                            )}
                            {record.isTechnicalGuest && (
                              <Badge variant="secondary" className="text-xs">
                                <Wrench className="h-3 w-3 mr-1" />
                                {t('event.technicalGuest')}
                              </Badge>
                            )}
                          </div>
                          <div className="flex items-center gap-2">
                            {record.status === 'attended' ? (
                              <Badge className="bg-green-500 hover:bg-green-600">{t('event.attended')}</Badge>
                            ) : (
                              <Badge variant="destructive">{t('event.noShow')}</Badge>
                            )}
                            {record.checkedInAt && (
                              <span className="text-xs text-muted-foreground">
                                {formatDateTime(record.checkedInAt)}
                              </span>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                  <Separator />
                </>
              )
            })()}

            {/* Metadata */}
            <div className="space-y-2">
              <Label className="text-lg font-semibold">{t('event.metadata')}</Label>
              <div className="grid grid-cols-2 gap-4 pl-4 text-sm text-muted-foreground">
                <div>
                  <Label className="text-muted-foreground">{t('event.createdAt')}</Label>
                  <div>{formatDateTime(event.created_at)}</div>
                </div>
                <div>
                  <Label className="text-muted-foreground">{t('event.updatedAt')}</Label>
                  <div>{formatDateTime(event.updated_at)}</div>
                </div>
                {event.google_calendar_event_id && (
                  <div className="col-span-2">
                    <Label className="text-muted-foreground">{t('event.googleCalendarSynced')}</Label>
                    <div>
                      <Badge variant="outline">{t('event.yes')}</Badge>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          <DialogFooter className="flex items-center justify-between">
            <div className="flex flex-col items-start gap-1">
              <Button
                variant="destructive"
                onClick={handleDelete}
                disabled={deleteMutation.isPending || !canDeleteEvent}
                data-testid="event-delete-btn"
                title={!canDeleteEvent ? t('event.cannotDeletePastEvent') : undefined}
              >
                {t('actions.delete')}
              </Button>
              {!canDeleteEvent && (
                <span className="text-xs text-muted-foreground">{t('event.cannotDeletePastEvent')}</span>
              )}
            </div>
            <div className="flex gap-2">
              {(isAdmin || canEdit) && onEdit && (
                <Button onClick={() => onEdit(event)}>
                  {t('common.edit')}
                </Button>
              )}
              <Button variant="outline" onClick={() => onOpenChange(false)}>
                {t('actions.close')}
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('event.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('event.deleteConfirmMessage')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>
              {t('actions.cancel')}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              disabled={deleteMutation.isPending}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleteMutation.isPending ? t('common.loading') : t('actions.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
