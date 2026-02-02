import { useEffect, useState, useRef } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { format, addMinutes } from 'date-fns'
import { adminClassOccurrencesApi, adminClassOccurrenceKeys } from '@/api/admin/classOccurrences'
import { classTemplatesApi, adminKeys } from '@/api/admin'
import { roomsApi, roomKeys } from '@/api/rooms'
import { usersApi } from '@/api/admin'
import { classKeys } from '@/api/classes'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useToast } from '@/hooks/use-toast'
import type { ClassOccurrence } from '@/types/class'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

interface ConflictData {
  conflicts: Array<{
    event_id: number
    event_type: string
    starts_at: string
    ends_at: string
    overlap_minutes: number
  }>
  requires_confirmation: boolean
}

// Validation schema
const classOccurrenceSchema = z.object({
  class_template_id: z.string().min(1, 'classOccurrenceForm.validation.templateRequired'),
  room_id: z.string().min(1, 'form.validation.roomRequired'),
  trainer_id: z.string().min(1, 'classOccurrenceForm.validation.trainerRequired'),
  starts_at: z.string().min(1, 'form.validation.dateRequired'),
  duration_minutes: z.number().min(15, 'form.validation.durationMin').max(480, 'form.validation.durationMax'),
  max_capacity: z.number().min(1, 'classOccurrenceForm.validation.capacityRequired'),
  is_recurring: z.boolean().default(false),
  repeat_from: z.string().optional(),
  repeat_until: z.string().optional(),
}).refine((data) => {
  // If recurring, both dates are required
  if (data.is_recurring && (!data.repeat_from || !data.repeat_until)) {
    return false
  }
  return true
}, {
  message: 'classOccurrenceForm.validation.intervalRequired',
  path: ['repeat_until'],
})

type ClassOccurrenceFormData = z.infer<typeof classOccurrenceSchema>

interface ClassOccurrenceFormModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  initialData?: {
    starts_at?: string
    duration_minutes?: number
  }
  editingOccurrence?: ClassOccurrence | null
  onSuccess?: () => void
}

export function ClassOccurrenceFormModal({
  open,
  onOpenChange,
  initialData,
  editingOccurrence,
  onSuccess,
}: ClassOccurrenceFormModalProps) {
  const { t } = useTranslation('calendar')
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [showConflictDialog, setShowConflictDialog] = useState(false)
  const [conflictData, setConflictData] = useState<ConflictData | null>(null)
  const pendingUpdateRef = useRef<ClassOccurrenceFormData | null>(null)
  const [showPastEventDialog, setShowPastEventDialog] = useState(false)
  const pendingFormDataRef = useRef<ClassOccurrenceFormData | null>(null)

  const isEditing = !!editingOccurrence

  const form = useForm<ClassOccurrenceFormData>({
    resolver: zodResolver(classOccurrenceSchema),
    defaultValues: {
      class_template_id: '',
      room_id: '',
      trainer_id: '',
      starts_at: '',
      duration_minutes: 60,
      max_capacity: 10,
      is_recurring: false,
      repeat_from: '',
      repeat_until: '',
    },
  })

  // Fetch class templates
  const { data: templates, isLoading: templatesLoading } = useQuery({
    queryKey: adminKeys.classTemplatesList({ is_active: true }),
    queryFn: () => classTemplatesApi.list({ is_active: true }),
    staleTime: 10 * 60 * 1000,
    enabled: open,
  })

  // Fetch rooms
  const { data: rooms, isLoading: roomsLoading } = useQuery({
    queryKey: roomKeys.list(),
    queryFn: () => roomsApi.list(),
    staleTime: 10 * 60 * 1000,
    enabled: open,
  })

  // Fetch staff users
  const { data: staffUsers, isLoading: staffLoading } = useQuery({
    queryKey: adminKeys.usersList({ role: 'staff' }),
    queryFn: () => usersApi.list({ role: 'staff' }),
    staleTime: 10 * 60 * 1000,
    enabled: open,
  })

  // Set initial data when modal opens
  useEffect(() => {
    if (open) {
      if (editingOccurrence) {
        // Editing mode
        const startsAt = new Date(editingOccurrence.starts_at)
        const endsAt = new Date(editingOccurrence.ends_at)
        const durationMinutes = Math.round((endsAt.getTime() - startsAt.getTime()) / 60000)

        form.reset({
          class_template_id: String(editingOccurrence.class_template_id),
          room_id: String(editingOccurrence.room_id),
          trainer_id: editingOccurrence.trainer_id ? String(editingOccurrence.trainer_id) : '',
          starts_at: format(startsAt, "yyyy-MM-dd'T'HH:mm"),
          duration_minutes: durationMinutes,
          max_capacity: editingOccurrence.capacity_override || editingOccurrence.class_template?.capacity || 10,
        })
      } else {
        // Creating new - reset form with defaults (60 min duration)
        form.reset({
          class_template_id: '',
          room_id: '',
          trainer_id: '',
          starts_at: initialData?.starts_at ? format(new Date(initialData.starts_at), "yyyy-MM-dd'T'HH:mm") : '',
          duration_minutes: initialData?.duration_minutes || 60,
          max_capacity: 10,
          is_recurring: false,
          repeat_from: '',
          repeat_until: '',
        })
      }
    }
  }, [open, initialData, editingOccurrence, form])

  // When template changes, update default capacity
  const selectedTemplateId = form.watch('class_template_id')
  useEffect(() => {
    if (selectedTemplateId && templates && !isEditing) {
      const template = templates.find(t => String(t.id) === selectedTemplateId)
      if (template) {
        form.setValue('max_capacity', template.default_capacity)
        if (template.duration_minutes) {
          form.setValue('duration_minutes', template.duration_minutes)
        }
      }
    }
  }, [selectedTemplateId, templates, form, isEditing])

  // Helper to format date without timezone offset (local time)
  const formatLocalDateTime = (date: Date) => format(date, "yyyy-MM-dd'T'HH:mm:ss")

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: ClassOccurrenceFormData) => {
      const startsAt = new Date(data.starts_at)
      const endsAt = addMinutes(startsAt, data.duration_minutes)

      return adminClassOccurrencesApi.create({
        class_template_id: data.class_template_id,
        room_id: data.room_id,
        trainer_id: data.trainer_id,
        starts_at: formatLocalDateTime(startsAt),
        ends_at: formatLocalDateTime(endsAt),
        max_capacity: data.max_capacity,
        is_recurring: data.is_recurring,
        repeat_from: data.is_recurring && data.repeat_from ? data.repeat_from : undefined,
        repeat_until: data.is_recurring && data.repeat_until ? data.repeat_until : undefined,
      })
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: classKeys.lists() })
      queryClient.invalidateQueries({ queryKey: adminClassOccurrenceKeys.all })
      toast({ title: t('classOccurrenceForm.success.created') })
      handleClose()
      onSuccess?.()
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}
      let errorMessage = t('classOccurrenceForm.errors.createFailed')
      if (status === 409) errorMessage = t('errors.conflict')
      else if (status === 422 && data?.message) errorMessage = data.message
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: (params: { data: ClassOccurrenceFormData; forceOverride?: boolean }) => {
      const { data, forceOverride } = params
      const startsAt = new Date(data.starts_at)
      const endsAt = addMinutes(startsAt, data.duration_minutes)

      return adminClassOccurrencesApi.update(editingOccurrence!.id, {
        starts_at: formatLocalDateTime(startsAt),
        ends_at: formatLocalDateTime(endsAt),
        room_id: data.room_id,
        trainer_id: data.trainer_id,
        capacity: data.max_capacity,
        force_override: forceOverride,
      })
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: classKeys.lists() })
      queryClient.invalidateQueries({ queryKey: adminClassOccurrenceKeys.all })
      toast({ title: t('classOccurrenceForm.success.updated') })
      pendingUpdateRef.current = null
      handleClose()
      onSuccess?.()
    },
    onError: (error: AxiosError<ApiError & { errors?: ConflictData }>) => {
      const { status, data } = error.response ?? {}

      // Check if this is a conflict that requires confirmation
      if (status === 409 && data?.errors?.requires_confirmation) {
        setConflictData(data.errors)
        setShowConflictDialog(true)
        return
      }

      let errorMessage = t('classOccurrenceForm.errors.updateFailed')
      if (status === 409) errorMessage = t('errors.conflict')
      else if (status === 422 && data?.message) errorMessage = data.message
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Handle force override after user confirms
  const handleForceOverride = () => {
    if (pendingUpdateRef.current) {
      updateMutation.mutate({ data: pendingUpdateRef.current, forceOverride: true })
    }
    setShowConflictDialog(false)
    setConflictData(null)
  }

  const handleCancelConflict = () => {
    setShowConflictDialog(false)
    setConflictData(null)
    pendingUpdateRef.current = null
  }

  const isPending = createMutation.isPending || updateMutation.isPending

  // Helper to check if date is in the past
  const isDateInPast = (dateString: string): boolean => {
    const eventDate = new Date(dateString)
    const now = new Date()
    return eventDate < now
  }

  // Process form submission (called after past date confirmation if needed)
  const processSubmit = (data: ClassOccurrenceFormData) => {
    if (isEditing) {
      // Store pending data for potential force override
      pendingUpdateRef.current = data
      updateMutation.mutate({ data })
    } else {
      createMutation.mutate(data)
    }
  }

  const onSubmit = form.handleSubmit((data) => {
    // Check if the event date is in the past (only for new events, not edits)
    if (!isEditing && isDateInPast(data.starts_at)) {
      pendingFormDataRef.current = data
      setShowPastEventDialog(true)
      return
    }

    // Process submission directly
    processSubmit(data)
  })

  // Handle past event confirmation
  const handleConfirmPastEvent = () => {
    if (pendingFormDataRef.current) {
      processSubmit(pendingFormDataRef.current)
      pendingFormDataRef.current = null
    }
    setShowPastEventDialog(false)
  }

  const handleCancelPastEvent = () => {
    setShowPastEventDialog(false)
    pendingFormDataRef.current = null
  }

  const handleClose = () => {
    onOpenChange(false)
    form.reset()
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {isEditing
              ? t('classOccurrenceForm.editTitle')
              : t('classOccurrenceForm.createTitle')
            }
          </DialogTitle>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-4">
          {/* Class Template */}
          <div className="space-y-2">
            <Label htmlFor="class-template">
              {t('classOccurrenceForm.template')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Controller
              name="class_template_id"
              control={form.control}
              render={({ field }) => (
                <Select
                  value={field.value}
                  onValueChange={field.onChange}
                  disabled={templatesLoading || isEditing}
                >
                  <SelectTrigger id="class-template">
                    <SelectValue placeholder={templatesLoading ? t('common.loading') : t('classOccurrenceForm.selectTemplate')} />
                  </SelectTrigger>
                  <SelectContent>
                    {templates?.map((template) => (
                      <SelectItem key={template.id} value={String(template.id)}>
                        {template.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
            {form.formState.errors.class_template_id && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.class_template_id.message ?? '')}
              </p>
            )}
          </div>

          {/* Trainer */}
          <div className="space-y-2">
            <Label htmlFor="trainer">
              {t('classOccurrenceForm.trainer')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Controller
              name="trainer_id"
              control={form.control}
              render={({ field }) => (
                <Select
                  value={field.value}
                  onValueChange={field.onChange}
                  disabled={staffLoading}
                >
                  <SelectTrigger id="trainer">
                    <SelectValue placeholder={staffLoading ? t('common.loading') : t('classOccurrenceForm.selectTrainer')} />
                  </SelectTrigger>
                  <SelectContent>
                    {staffUsers?.data?.map((user) => (
                      <SelectItem key={user.staff_profile?.id} value={String(user.staff_profile?.id)}>
                        {user.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
            {form.formState.errors.trainer_id && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.trainer_id.message ?? '')}
              </p>
            )}
          </div>

          {/* Room */}
          <div className="space-y-2">
            <Label htmlFor="room">
              {t('form.room')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Controller
              name="room_id"
              control={form.control}
              render={({ field }) => (
                <Select
                  value={field.value}
                  onValueChange={field.onChange}
                  disabled={roomsLoading}
                >
                  <SelectTrigger id="room">
                    <SelectValue placeholder={roomsLoading ? t('common.loading') : t('form.room')} />
                  </SelectTrigger>
                  <SelectContent>
                    {rooms?.map((room) => (
                      <SelectItem key={room.id} value={String(room.id)}>
                        {room.name} ({room.facility})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
            {form.formState.errors.room_id && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.room_id.message ?? '')}
              </p>
            )}
          </div>

          {/* Start Time */}
          <div className="space-y-2">
            <Label htmlFor="starts-at">
              {t('form.startTime')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Input
              id="starts-at"
              type="datetime-local"
              {...form.register('starts_at')}
            />
            {form.formState.errors.starts_at && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.starts_at.message ?? '')}
              </p>
            )}
          </div>

          {/* Duration */}
          <div className="space-y-2">
            <Label htmlFor="duration">
              {t('form.duration')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Input
              id="duration"
              type="number"
              min={15}
              max={480}
              step={15}
              {...form.register('duration_minutes', { valueAsNumber: true })}
            />
            {form.formState.errors.duration_minutes && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.duration_minutes.message ?? '')}
              </p>
            )}
          </div>

          {/* Max Capacity */}
          <div className="space-y-2">
            <Label htmlFor="max-capacity">
              {t('classOccurrenceForm.maxCapacity')}
              <span className="text-destructive ml-1">*</span>
            </Label>
            <Input
              id="max-capacity"
              type="number"
              min={1}
              {...form.register('max_capacity', { valueAsNumber: true })}
              disabled={isEditing}
            />
            {form.formState.errors.max_capacity && (
              <p className="text-sm text-destructive">
                {t(form.formState.errors.max_capacity.message ?? '')}
              </p>
            )}
          </div>

          {/* Recurring Event (only for create mode) */}
          {!isEditing && (
            <>
              <div className="flex items-center space-x-2 pt-4 pb-2 border-t">
                <Controller
                  name="is_recurring"
                  control={form.control}
                  render={({ field }) => (
                    <Checkbox
                      id="is-recurring"
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  )}
                />
                <Label htmlFor="is-recurring" className="font-normal cursor-pointer">
                  Ismétlődő esemény (minden héten)
                </Label>
              </div>

              {/* Date interval - shown only when is_recurring is checked */}
              {form.watch('is_recurring') && (
                <div className="space-y-4 p-4 bg-muted/50 rounded-lg">
                  <p className="text-sm text-muted-foreground">
                    Az óra minden héten ugyanezen a napon és időpontban létrejön a megadott intervallumon belül.
                  </p>

                  <div className="grid grid-cols-2 gap-4">
                    {/* Repeat From */}
                    <div className="space-y-2">
                      <Label htmlFor="repeat-from">
                        Intervallum kezdete
                        <span className="text-destructive ml-1">*</span>
                      </Label>
                      <Input
                        id="repeat-from"
                        type="date"
                        {...form.register('repeat_from')}
                      />
                    </div>

                    {/* Repeat Until */}
                    <div className="space-y-2">
                      <Label htmlFor="repeat-until">
                        Intervallum vége
                        <span className="text-destructive ml-1">*</span>
                      </Label>
                      <Input
                        id="repeat-until"
                        type="date"
                        {...form.register('repeat_until')}
                        min={form.watch('repeat_from')}
                      />
                    </div>
                  </div>

                  {form.formState.errors.repeat_until && (
                    <p className="text-sm text-destructive">
                      Mindkét dátum megadása kötelező
                    </p>
                  )}
                </div>
              )}
            </>
          )}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={handleClose} disabled={isPending}>
              {t('actions.cancel')}
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending
                ? t('common.loading')
                : isEditing
                  ? t('actions.update')
                  : t('actions.create')
              }
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>

      {/* Conflict Confirmation Dialog */}
      <AlertDialog open={showConflictDialog} onOpenChange={setShowConflictDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('conflict.title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('conflict.message')}
              {conflictData?.conflicts && conflictData.conflicts.length > 0 && (
                <div className="mt-2 text-sm text-muted-foreground">
                  {conflictData.conflicts.map((conflict, index) => (
                    <div key={index}>
                      {conflict.overlap_minutes} {t('conflict.minutesOverlap')}
                    </div>
                  ))}
                </div>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={handleCancelConflict}>{t('actions.cancel')}</AlertDialogCancel>
            <Button onClick={handleForceOverride} disabled={updateMutation.isPending}>
              {updateMutation.isPending ? t('common.loading') : t('conflict.continueAnyway')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Past Event Confirmation Dialog */}
      <AlertDialog open={showPastEventDialog} onOpenChange={setShowPastEventDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('pastEventConfirmation.title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('pastEventConfirmation.message')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={handleCancelPastEvent}>{t('pastEventConfirmation.cancel')}</AlertDialogCancel>
            <Button onClick={handleConfirmPastEvent} disabled={isPending}>
              {isPending ? t('common.loading') : t('pastEventConfirmation.confirm')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Dialog>
  )
}
