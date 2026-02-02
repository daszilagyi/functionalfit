import { useState, useEffect, useRef } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { format } from 'date-fns'
import { eventsApi, eventKeys } from '@/api/events'
import { roomsApi, roomKeys } from '@/api/rooms'
import { serviceTypesApi, staffServiceTypesApi, serviceTypeKeys, pricingResolveApi } from '@/api/serviceTypes'
import { createEventSchema, CreateEventFormData } from '@/lib/validations/event'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MultiClientPicker } from './MultiClientPicker'
import { StaffPicker } from './StaffPicker'
import { RecurringPreviewDialog } from './RecurringPreviewDialog'
import { useToast } from '@/hooks/use-toast'
import type { Event, RecurringPreviewDate } from '@/types/event'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'
import type { ServiceType } from '@/types/serviceType'

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

interface EventFormModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  initialData?: {
    starts_at?: string
    duration_minutes?: number
  }
  editingEvent?: Event | null
  isAdmin?: boolean
  onSuccess?: () => void
}

export function EventFormModal({ open, onOpenChange, initialData, editingEvent, isAdmin, onSuccess }: EventFormModalProps) {
  const { t } = useTranslation('calendar')
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [, setSelectedClientName] = useState('')
  const [, setSelectedStaffName] = useState('')
  const [selectedClientIds, setSelectedClientIds] = useState<number[]>([])
  const [showConflictDialog, setShowConflictDialog] = useState(false)
  const [conflictData, setConflictData] = useState<ConflictData | null>(null)
  const [showPastEventDialog, setShowPastEventDialog] = useState(false)
  const pendingFormDataRef = useRef<CreateEventFormData | null>(null)
  const [selectedServiceTypeId, setSelectedServiceTypeId] = useState<number | null>(null)
  // Recurring event state
  const [isRecurring, setIsRecurring] = useState(false)
  const [repeatFrom, setRepeatFrom] = useState('')
  const [repeatUntil, setRepeatUntil] = useState('')
  const [showRecurringPreviewDialog, setShowRecurringPreviewDialog] = useState(false)
  const [recurringPreviewDates, setRecurringPreviewDates] = useState<RecurringPreviewDate[]>([])
  const [recurringFormData, setRecurringFormData] = useState<CreateEventFormData | null>(null)
  const [resolvedPricing, setResolvedPricing] = useState<{
    entry_fee_brutto: number
    trainer_fee_brutto: number
    source: 'client_price_code' | 'service_type_default'
  } | null>(null)
  const pendingUpdateRef = useRef<{ id: string; updates: any } | null>(null)
  // Track if we've initialized for the current open session to avoid re-resetting on re-renders
  const hasInitializedRef = useRef(false)
  const isEditMode = !!editingEvent

  const form = useForm<CreateEventFormData>({
    resolver: zodResolver(createEventSchema),
    defaultValues: {
      type: 'INDIVIDUAL',
      staff_id: '',
      client_id: '',
      room_id: '',
      starts_at: '',
      duration_minutes: 60,
      notes: '',
    },
  })

  const selectedType = form.watch('type')

  const { data: rooms, isLoading: roomsLoading } = useQuery({
    queryKey: roomKeys.list(),
    queryFn: () => roomsApi.list(),
    staleTime: 10 * 60 * 1000,
  })

  // Fetch service types for the dropdown (use staff API for non-admin)
  const { data: serviceTypes } = useQuery({
    queryKey: [...serviceTypeKeys.lists(), isAdmin ? 'admin' : 'staff'],
    queryFn: isAdmin ? serviceTypesApi.list : staffServiceTypesApi.list,
    staleTime: 10 * 60 * 1000,
  })

  // Auto-resolve pricing when client and service type are selected
  useEffect(() => {
    const resolvePrice = async () => {
      // Only resolve for INDIVIDUAL events with a client and service type selected
      if (selectedType !== 'INDIVIDUAL' || selectedClientIds.length === 0 || !selectedServiceTypeId) {
        setResolvedPricing(null)
        return
      }

      // Get the first real client ID (positive number)
      const firstRealClientId = selectedClientIds.find(id => id > 0)
      if (!firstRealClientId) {
        setResolvedPricing(null)
        return
      }

      try {
        const pricing = await pricingResolveApi.resolveByIds(firstRealClientId, selectedServiceTypeId)
        setResolvedPricing({
          entry_fee_brutto: pricing.entry_fee_brutto,
          trainer_fee_brutto: pricing.trainer_fee_brutto,
          source: pricing.source,
        })
      } catch {
        // If resolve fails, just use service type defaults
        const serviceType = serviceTypes?.find((st: ServiceType) => st.id === selectedServiceTypeId)
        if (serviceType) {
          setResolvedPricing({
            entry_fee_brutto: serviceType.default_entry_fee_brutto,
            trainer_fee_brutto: serviceType.default_trainer_fee_brutto,
            source: 'service_type_default',
          })
        }
      }
    }

    resolvePrice()
  }, [selectedClientIds, selectedServiceTypeId, selectedType, serviceTypes])

  // Populate form when editing
  useEffect(() => {
    if (editingEvent && open) {
      hasInitializedRef.current = true
      form.setValue('type', editingEvent.type)
      if (editingEvent.room_id) {
        form.setValue('room_id', editingEvent.room_id.toString())
      }
      form.setValue('starts_at', editingEvent.starts_at)
      form.setValue('duration_minutes', editingEvent.duration_minutes)
      form.setValue('notes', editingEvent.notes || '')
      if (editingEvent.staff_id) {
        form.setValue('staff_id', editingEvent.staff_id.toString())
      }

      // Populate multi-client picker with main + additional clients
      // Support both snake_case (from API) and camelCase naming
      const allClientIds: number[] = []
      if (editingEvent.client_id) {
        allClientIds.push(parseInt(editingEvent.client_id))
      }
      const additionalClientsList = editingEvent.additional_clients || editingEvent.additionalClients || []
      if (additionalClientsList.length > 0) {
        // Expand based on quantity - for technical guests, use negative IDs
        for (const client of additionalClientsList) {
          const quantity = client.pivot?.quantity ?? 1
          const clientId = parseInt(client.id)
          const isTechnicalGuest = client.is_technical_guest

          for (let i = 0; i < quantity; i++) {
            if (isTechnicalGuest) {
              // Generate unique negative ID for each technical guest instance
              allClientIds.push(-(Date.now() + Math.floor(Math.random() * 1000) + i))
            } else {
              allClientIds.push(clientId)
            }
          }
        }
      }
      setSelectedClientIds(allClientIds)

      // Keep old client_id for backward compatibility
      if (editingEvent.client_id) {
        form.setValue('client_id', editingEvent.client_id.toString())
      }

      // Set service type if editing
      if (editingEvent.service_type_id) {
        setSelectedServiceTypeId(editingEvent.service_type_id)
      }
    } else if (open && !editingEvent && !hasInitializedRef.current) {
      // Reset form to defaults when opening for new event (only once per open session)
      hasInitializedRef.current = true
      form.reset({
        type: 'INDIVIDUAL',
        staff_id: '',
        client_id: '',
        room_id: '',
        starts_at: initialData?.starts_at || '',
        duration_minutes: initialData?.duration_minutes || 60,
        notes: '',
      })
      setSelectedClientIds([])
      setSelectedServiceTypeId(null)
      setResolvedPricing(null)
      // Reset recurring state
      setIsRecurring(false)
      setRepeatFrom('')
      setRepeatUntil('')
    } else if (!open) {
      // Reset when closing - also reset the initialization flag
      hasInitializedRef.current = false
      setSelectedClientIds([])
      setSelectedServiceTypeId(null)
      setResolvedPricing(null)
      // Reset recurring state
      setIsRecurring(false)
      setRepeatFrom('')
      setRepeatUntil('')
      setShowRecurringPreviewDialog(false)
      setRecurringPreviewDates([])
      setRecurringFormData(null)
    }
  }, [editingEvent, initialData, open, form])

  // Sync selectedClientIds with form.client_id for validation
  useEffect(() => {
    if (selectedClientIds.length > 0) {
      form.setValue('client_id', String(selectedClientIds[0]))
    } else {
      form.setValue('client_id', '')
    }
  }, [selectedClientIds, form])

  const createMutation = useMutation({
    mutationFn: (data: CreateEventFormData) => {
      // Admin uses adminCreate endpoint with staff_id, staff uses create endpoint (gets their own staff_id from backend)
      return isAdmin ? eventsApi.adminCreate(data) : eventsApi.create(data)
    },
    onSuccess: async () => {
      await queryClient.refetchQueries({
        queryKey: eventKeys.all,
        type: 'active'
      })
      toast({ title: t('success.created') })
      onOpenChange(false)
      form.reset()
      setSelectedClientName('')
      setSelectedStaffName('')
      setSelectedClientIds([])
      setSelectedServiceTypeId(null)
      setResolvedPricing(null)
      onSuccess?.()
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}
      let errorMessage = t('errors.createFailed')
      if (status === 409) errorMessage = t('errors.conflict')
      else if (status === 422 && data?.message) errorMessage = data.message
      else if (status === 423) errorMessage = t('errors.locked')
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: { id: string; updates: any }) =>
      isAdmin ? eventsApi.adminUpdate(data.id, data.updates) : eventsApi.update(data.id, data.updates),
    onSuccess: async () => {
      // Invalidate and refetch all event queries
      await queryClient.invalidateQueries({ queryKey: eventKeys.all })
      toast({ title: t('success.updated') })
      onOpenChange(false)
      form.reset()
      setSelectedClientName('')
      setSelectedStaffName('')
      setSelectedClientIds([])
      setSelectedServiceTypeId(null)
      setResolvedPricing(null)
      pendingUpdateRef.current = null
      onSuccess?.()
    },
    onError: (error: AxiosError<ApiError & { errors?: ConflictData }>) => {
      const { status, data } = error.response ?? {}

      // Check if this is a conflict that requires confirmation
      // API response structure: { success: false, message: "...", errors: { conflicts: [...], requires_confirmation: true } }
      if (status === 409 && data?.errors?.requires_confirmation) {
        setConflictData(data.errors)
        setShowConflictDialog(true)
        return
      }

      let errorMessage = t('errors.updateFailed')
      if (status === 409) errorMessage = t('errors.conflict')
      else if (status === 422 && data?.message) errorMessage = data.message
      else if (status === 423) errorMessage = t('errors.locked')
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Recurring preview mutation
  const previewRecurringMutation = useMutation({
    mutationFn: (data: any) => {
      return isAdmin ? eventsApi.adminPreviewRecurring(data) : eventsApi.previewRecurring(data)
    },
    onSuccess: (response) => {
      setRecurringPreviewDates(response.dates)
      setShowRecurringPreviewDialog(true)
    },
    onError: (error: AxiosError<ApiError>) => {
      const { data } = error.response ?? {}
      toast({
        variant: 'destructive',
        title: t('common.error'),
        description: data?.message || t('errors.createFailed'),
      })
    },
  })

  // Recurring create mutation
  const createRecurringMutation = useMutation({
    mutationFn: (data: any) => {
      return isAdmin ? eventsApi.adminCreateRecurring(data) : eventsApi.createRecurring(data)
    },
    onSuccess: async (response) => {
      await queryClient.refetchQueries({
        queryKey: eventKeys.all,
        type: 'active'
      })
      const skippedCount = response.skipped_dates?.length || 0
      if (skippedCount > 0) {
        toast({ title: t('success.recurringCreatedWithSkips', { count: response.count, skipped: skippedCount }) })
      } else {
        toast({ title: t('success.recurringCreated', { count: response.count }) })
      }
      setShowRecurringPreviewDialog(false)
      onOpenChange(false)
      form.reset()
      setSelectedClientName('')
      setSelectedStaffName('')
      setSelectedClientIds([])
      setSelectedServiceTypeId(null)
      setResolvedPricing(null)
      setIsRecurring(false)
      setRepeatFrom('')
      setRepeatUntil('')
      setRecurringFormData(null)
      onSuccess?.()
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}
      let errorMessage = t('errors.createFailed')
      if (status === 409) errorMessage = t('errors.conflict')
      else if (status === 422 && data?.message) errorMessage = data.message
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Handle force override after user confirms
  const handleForceOverride = () => {
    if (pendingUpdateRef.current) {
      const { id, updates } = pendingUpdateRef.current
      // Close dialog first, then mutate - the mutation's onSuccess will handle the rest
      setShowConflictDialog(false)
      setConflictData(null)
      updateMutation.mutate({ id, updates: { ...updates, force_override: true } })
    } else {
      setShowConflictDialog(false)
      setConflictData(null)
    }
  }

  const handleCancelConflict = () => {
    setShowConflictDialog(false)
    setConflictData(null)
    pendingUpdateRef.current = null
  }

  // Helper to check if date is in the past
  const isDateInPast = (dateString: string): boolean => {
    const eventDate = new Date(dateString)
    const now = new Date()
    return eventDate < now
  }

  // Process form submission (called after past date confirmation if needed)
  const processSubmit = (data: CreateEventFormData) => {
    // Helper to format date without timezone offset (local time)
    const formatLocalDateTime = (date: Date) => format(date, "yyyy-MM-dd'T'HH:mm:ss")

    if (isEditMode && editingEvent) {
      // Calculate ends_at from starts_at and duration_minutes
      const startsAt = new Date(data.starts_at)
      const endsAt = new Date(startsAt.getTime() + data.duration_minutes * 60000)

      const updates: any = {
        type: data.type,
        room_id: parseInt(data.room_id),
        starts_at: formatLocalDateTime(startsAt),
        ends_at: formatLocalDateTime(endsAt),
        notes: data.notes || null,
      }

      if (isAdmin && editingEvent.staff_id) {
        updates.staff_id = editingEvent.staff_id
      }

      // Handle multi-client and service type for INDIVIDUAL events
      if (data.type === 'INDIVIDUAL' && selectedClientIds.length > 0) {
        // Only set client_id if the first guest is a real client (positive ID)
        // Technical guests (negative IDs) should only go in additional_client_ids
        const firstId = selectedClientIds[0]
        if (firstId > 0) {
          updates.client_id = firstId
          updates.additional_client_ids = selectedClientIds.slice(1)
        } else {
          // All guests are technical, put them all in additional_client_ids
          updates.client_id = null
          updates.additional_client_ids = selectedClientIds
        }
        // Include service type for INDIVIDUAL events
        if (selectedServiceTypeId) {
          updates.service_type_id = selectedServiceTypeId
        }
      } else {
        updates.client_id = null
        updates.additional_client_ids = []
      }

      // Store the pending update for potential force override
      pendingUpdateRef.current = { id: editingEvent.id.toString(), updates }
      updateMutation.mutate({ id: editingEvent.id.toString(), updates })
    } else {
      // Calculate ends_at from starts_at and duration_minutes for create
      const startsAt = new Date(data.starts_at)
      const endsAt = new Date(startsAt.getTime() + (data.duration_minutes || 60) * 60000)

      const createData: any = {
        type: data.type,
        room_id: data.room_id,
        starts_at: formatLocalDateTime(startsAt),
        ends_at: formatLocalDateTime(endsAt),
        notes: data.notes || undefined,
      }

      // Admin must provide staff_id
      if (isAdmin && data.staff_id) {
        createData.staff_id = data.staff_id
      }

      // Handle multi-client and service type for INDIVIDUAL events
      if (data.type === 'INDIVIDUAL' && selectedClientIds.length > 0) {
        // Only set client_id if the first guest is a real client (positive ID)
        // Technical guests (negative IDs) should only go in additional_client_ids
        const firstId = selectedClientIds[0]
        if (firstId > 0) {
          createData.client_id = firstId
          createData.additional_client_ids = selectedClientIds.slice(1)
        } else {
          // All guests are technical, put them all in additional_client_ids
          createData.additional_client_ids = selectedClientIds
        }
        // Include service type for INDIVIDUAL events
        if (selectedServiceTypeId) {
          createData.service_type_id = selectedServiceTypeId
        }
      }

      createMutation.mutate(createData)
    }
  }

  const onSubmit = form.handleSubmit((data) => {
    // Validate INDIVIDUAL events have at least one guest
    if (data.type === 'INDIVIDUAL' && selectedClientIds.length === 0) {
      toast({
        variant: 'destructive',
        title: t('common.error'),
        description: t('form.validation.clientRequired'),
      })
      return
    }

    // Validate INDIVIDUAL events have a service type selected
    if (data.type === 'INDIVIDUAL' && !selectedServiceTypeId) {
      toast({
        variant: 'destructive',
        title: t('common.error'),
        description: t('form.validation.serviceTypeRequired'),
      })
      return
    }

    // Handle recurring events (only for new events, not edits)
    if (!isEditMode && isRecurring) {
      // Validate recurring interval
      if (!repeatFrom || !repeatUntil) {
        toast({
          variant: 'destructive',
          title: t('common.error'),
          description: t('recurring.validation.intervalRequired'),
        })
        return
      }

      // Store form data for later use in recurring preview confirm
      setRecurringFormData(data)

      // Build preview request
      const formatLocalDateTime = (date: Date) => format(date, "yyyy-MM-dd'T'HH:mm:ss")
      const startsAt = new Date(data.starts_at)
      const endsAt = new Date(startsAt.getTime() + (data.duration_minutes || 60) * 60000)

      const previewData: any = {
        type: data.type,
        room_id: data.room_id,
        starts_at: formatLocalDateTime(startsAt),
        ends_at: formatLocalDateTime(endsAt),
        repeat_from: repeatFrom,
        repeat_until: repeatUntil,
        is_recurring: true,
      }

      // Admin must provide staff_id
      if (isAdmin && data.staff_id) {
        previewData.staff_id = data.staff_id
      }

      // Handle multi-client and service type for INDIVIDUAL events (required by backend validation)
      if (data.type === 'INDIVIDUAL' && selectedClientIds.length > 0) {
        const firstId = selectedClientIds[0]
        if (firstId > 0) {
          previewData.client_id = firstId
          previewData.additional_client_ids = selectedClientIds.slice(1)
        } else {
          previewData.additional_client_ids = selectedClientIds
        }
        if (selectedServiceTypeId) {
          previewData.service_type_id = selectedServiceTypeId
        }
      }

      // Trigger preview API call
      previewRecurringMutation.mutate(previewData)
      return
    }

    // Check if the event date is in the past (only for new events, not edits)
    if (!isEditMode && isDateInPast(data.starts_at)) {
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

  // Handle recurring preview confirmation
  const handleRecurringConfirm = (skipDates: string[]) => {
    if (!recurringFormData) return

    const formatLocalDateTime = (date: Date) => format(date, "yyyy-MM-dd'T'HH:mm:ss")
    const startsAt = new Date(recurringFormData.starts_at)
    const endsAt = new Date(startsAt.getTime() + (recurringFormData.duration_minutes || 60) * 60000)

    const createData: any = {
      type: recurringFormData.type,
      room_id: recurringFormData.room_id,
      starts_at: formatLocalDateTime(startsAt),
      ends_at: formatLocalDateTime(endsAt),
      notes: recurringFormData.notes || undefined,
      is_recurring: true,
      repeat_from: repeatFrom,
      repeat_until: repeatUntil,
      skip_dates: skipDates,
    }

    // Admin must provide staff_id
    if (isAdmin && recurringFormData.staff_id) {
      createData.staff_id = recurringFormData.staff_id
    }

    // Handle multi-client and service type for INDIVIDUAL events
    if (recurringFormData.type === 'INDIVIDUAL' && selectedClientIds.length > 0) {
      const firstId = selectedClientIds[0]
      if (firstId > 0) {
        createData.client_id = firstId
        createData.additional_client_ids = selectedClientIds.slice(1)
      } else {
        createData.additional_client_ids = selectedClientIds
      }
      if (selectedServiceTypeId) {
        createData.service_type_id = selectedServiceTypeId
      }
    }

    createRecurringMutation.mutate(createData)
  }

  const handleRecurringCancel = () => {
    setShowRecurringPreviewDialog(false)
    setRecurringPreviewDates([])
    setRecurringFormData(null)
  }

  const handleClose = () => {
    onOpenChange(false)
    form.reset()
    setSelectedClientName('')
    setSelectedStaffName('')
    setSelectedClientIds([])
    setSelectedServiceTypeId(null)
    setResolvedPricing(null)
    // Reset recurring state
    setIsRecurring(false)
    setRepeatFrom('')
    setRepeatUntil('')
    setShowRecurringPreviewDialog(false)
    setRecurringPreviewDates([])
    setRecurringFormData(null)
  }

  const formatDateTimeLocal = (isoString: string) => {
    if (!isoString) return ''
    return format(new Date(isoString), "yyyy-MM-dd'T'HH:mm")
  }

  const isPending = createMutation.isPending || updateMutation.isPending || previewRecurringMutation.isPending || createRecurringMutation.isPending

  return (
    <>
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEditMode ? t('event.editEvent') : t('event.createEvent')}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="event-type">{t('form.type')}<span className="text-destructive ml-1">*</span></Label>
            <Controller name="type" control={form.control} render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="event-type" data-testid="event-type-select"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="INDIVIDUAL">{t('event.eventType.INDIVIDUAL')}</SelectItem>
                  <SelectItem value="BLOCK">{t('event.eventType.BLOCK')}</SelectItem>
                </SelectContent>
              </Select>
            )} />
            {form.formState.errors.type && (<p className="text-sm text-destructive">{t(form.formState.errors.type.message ?? '')}</p>)}
          </div>
          {isAdmin && (
            <Controller name="staff_id" control={form.control} render={({ field }) => (
              <StaffPicker value={field.value} onChange={(staffId, staffName) => { field.onChange(staffId); setSelectedStaffName(staffName) }} error={form.formState.errors.staff_id?.message} required />
            )} />
          )}
          {selectedType === 'INDIVIDUAL' && (
            <MultiClientPicker
              value={selectedClientIds}
              onChange={setSelectedClientIds}
              error={form.formState.errors.client_id?.message}
              required
            />
          )}
          {selectedType === 'INDIVIDUAL' && (
            <div className="space-y-2">
              <Label htmlFor="service-type">{t('form.serviceType')}<span className="text-destructive ml-1">*</span></Label>
              <Select
                value={selectedServiceTypeId?.toString() || ''}
                onValueChange={(value) => setSelectedServiceTypeId(value ? parseInt(value, 10) : null)}
              >
                <SelectTrigger id="service-type" data-testid="service-type-select">
                  <SelectValue placeholder={t('form.selectServiceType')} />
                </SelectTrigger>
                <SelectContent>
                  {serviceTypes?.filter((st: ServiceType) => st.is_active).map((st: ServiceType) => (
                    <SelectItem key={st.id} value={st.id.toString()}>
                      {st.name} ({st.code})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {resolvedPricing && (
                <div className="mt-2 p-3 bg-muted rounded-md text-sm">
                  <div className="flex justify-between">
                    <span>{t('form.entryFee')}:</span>
                    <span className="font-medium">{resolvedPricing.entry_fee_brutto.toLocaleString('hu-HU')} Ft</span>
                  </div>
                  <div className="flex justify-between mt-1">
                    <span>{t('form.trainerFee')}:</span>
                    <span className="font-medium">{resolvedPricing.trainer_fee_brutto.toLocaleString('hu-HU')} Ft</span>
                  </div>
                  <div className="text-xs text-muted-foreground mt-2">
                    {resolvedPricing.source === 'client_price_code'
                      ? t('form.pricingSourceClient')
                      : t('form.pricingSourceDefault')}
                  </div>
                </div>
              )}
            </div>
          )}
          <div className="space-y-2">
            <Label htmlFor="room">{t('form.room')}<span className="text-destructive ml-1">*</span></Label>
            <Controller name="room_id" control={form.control} render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange} disabled={roomsLoading}>
                <SelectTrigger id="room" data-testid="room-select"><SelectValue placeholder={roomsLoading ? t('common.loading') : t('form.room')} /></SelectTrigger>
                <SelectContent>{rooms?.map((room) => (<SelectItem key={room.id} value={String(room.id)}>{room.name} ({room.facility})</SelectItem>))}</SelectContent>
              </Select>
            )} />
            {form.formState.errors.room_id && (<p className="text-sm text-destructive">{t(form.formState.errors.room_id.message ?? '')}</p>)}
          </div>
          <div className="space-y-2">
            <Label htmlFor="starts-at">{t('form.startTime')}<span className="text-destructive ml-1">*</span></Label>
            <Controller
              name="starts_at"
              control={form.control}
              render={({ field }) => (
                <Input
                  id="starts-at"
                  type="datetime-local"
                  value={formatDateTimeLocal(field.value)}
                  onChange={(e) => {
                    const localDateTime = e.target.value
                    if (localDateTime) {
                      field.onChange(new Date(localDateTime).toISOString())
                    }
                  }}
                  data-testid="event-start-time-input"
                />
              )}
            />
            {form.formState.errors.starts_at && (<p className="text-sm text-destructive">{t(form.formState.errors.starts_at.message ?? '')}</p>)}
          </div>
          <div className="space-y-2">
            <Label htmlFor="duration">{t('form.duration')}<span className="text-destructive ml-1">*</span></Label>
            <Input id="duration" type="number" min={15} max={480} step={15} {...form.register('duration_minutes', { valueAsNumber: true })} data-testid="event-duration-input" />
            {form.formState.errors.duration_minutes && (<p className="text-sm text-destructive">{t(form.formState.errors.duration_minutes.message ?? '')}</p>)}
          </div>
          <div className="space-y-2">
            <Label htmlFor="notes">{t('form.notes')}</Label>
            <Textarea id="notes" {...form.register('notes')} placeholder={t('form.notesPlaceholder')} data-testid="event-notes-input" rows={4} />
            {form.formState.errors.notes && (<p className="text-sm text-destructive">{t(form.formState.errors.notes.message ?? '')}</p>)}
          </div>

          {/* Recurring Event (only for INDIVIDUAL type and create mode) */}
          {!isEditMode && selectedType === 'INDIVIDUAL' && (
            <>
              <div className="flex items-center space-x-2 pt-4 pb-2 border-t">
                <Checkbox
                  id="is-recurring"
                  checked={isRecurring}
                  onCheckedChange={(checked) => setIsRecurring(checked === true)}
                  data-testid="event-recurring-checkbox"
                />
                <Label htmlFor="is-recurring" className="font-normal cursor-pointer">
                  {t('recurring.label')}
                </Label>
              </div>

              {/* Date interval - shown only when is_recurring is checked */}
              {isRecurring && (
                <div className="space-y-4 p-4 bg-muted/50 rounded-lg">
                  <p className="text-sm text-muted-foreground">
                    {t('recurring.description')}
                  </p>

                  <div className="grid grid-cols-2 gap-4">
                    {/* Repeat From */}
                    <div className="space-y-2">
                      <Label htmlFor="repeat-from">
                        {t('recurring.repeatFrom')}
                        <span className="text-destructive ml-1">*</span>
                      </Label>
                      <Input
                        id="repeat-from"
                        type="date"
                        value={repeatFrom}
                        onChange={(e) => setRepeatFrom(e.target.value)}
                        data-testid="event-repeat-from-input"
                      />
                    </div>

                    {/* Repeat Until */}
                    <div className="space-y-2">
                      <Label htmlFor="repeat-until">
                        {t('recurring.repeatUntil')}
                        <span className="text-destructive ml-1">*</span>
                      </Label>
                      <Input
                        id="repeat-until"
                        type="date"
                        value={repeatUntil}
                        onChange={(e) => setRepeatUntil(e.target.value)}
                        min={repeatFrom}
                        data-testid="event-repeat-until-input"
                      />
                    </div>
                  </div>
                </div>
              )}
            </>
          )}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={handleClose} disabled={isPending}>{t('actions.cancel')}</Button>
            <Button type="submit" disabled={isPending} data-testid="event-submit-btn">{isPending ? t('common.loading') : (isEditMode ? t('common.save') : t('actions.create'))}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    {/* Conflict Confirmation Dialog */}
    <AlertDialog open={showConflictDialog} onOpenChange={setShowConflictDialog}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('conflict.title')}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('conflict.message')}
            {conflictData?.conflicts && conflictData.conflicts.length > 0 && (
              <ul className="mt-2 text-sm">
                {conflictData.conflicts.map((conflict, index) => (
                  <li key={index} className="py-1">
                    • {format(new Date(conflict.starts_at), 'HH:mm')} - {format(new Date(conflict.ends_at), 'HH:mm')}
                    {' '}({conflict.overlap_minutes} {t('conflict.minutesOverlap')})
                  </li>
                ))}
              </ul>
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

    {/* Recurring Preview Dialog */}
    <RecurringPreviewDialog
      open={showRecurringPreviewDialog}
      onOpenChange={setShowRecurringPreviewDialog}
      dates={recurringPreviewDates}
      onConfirm={handleRecurringConfirm}
      onCancel={handleRecurringCancel}
      isPending={createRecurringMutation.isPending}
    />
  </>
  )
}
