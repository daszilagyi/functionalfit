import { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import FullCalendar from '@fullcalendar/react'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import { eventsApi, eventKeys } from '@/api/events'
import { roomsApi, roomKeys } from '@/api/rooms'
import { classesApi, classKeys } from '@/api/classes'
import { Check, X, AlertTriangle, Plus } from 'lucide-react'
import { isSameDayMove } from '@/lib/validations/event'
import { useToast } from '@/hooks/use-toast'
import { EventFormModal } from '@/components/calendar/EventFormModal'
import { EventDetailsModal } from '@/components/calendar/EventDetailsModal'
import { ClassOccurrenceDetailsModal } from '@/components/calendar/ClassOccurrenceDetailsModal'
import { ClassOccurrenceFormModal } from '@/components/calendar/ClassOccurrenceFormModal'
import { EventTypeSelectorModal } from '@/components/calendar/EventTypeSelectorModal'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
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
import type { Event } from '@/types/event'
import type { ClassOccurrence } from '@/types/class'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'
import type { EventClickArg, DateSelectArg, DatesSetArg, EventDropArg } from '@fullcalendar/core'
import type { EventResizeDoneArg } from '@fullcalendar/interaction'

export default function CalendarPage() {
  const { t, i18n } = useTranslation('calendar')
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const calendarRef = useRef<FullCalendar>(null)
  const { user } = useAuth()

  const isAdmin = user?.role === 'admin'
  const isStaff = user?.role === 'staff'
  const currentStaffId = user?.staffProfile?.id

  // Check if current user owns an event (admin owns all, staff owns only their own)
  const isEventOwner = (event: Event): boolean => {
    if (isAdmin) return true
    if (!currentStaffId) return false
    return String(event.staff_id) === String(currentStaffId)
  }

  const [dateRange, setDateRange] = useState({
    start: new Date(),
    end: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000), // 7 days ahead
  })
  const [currentView, setCurrentView] = useState<'timeGridWeek' | 'timeGridDay' | 'timeGridTwoDay'>('timeGridWeek')
  const [selectedRoomId, setSelectedRoomId] = useState<string | null>(null)
  const [showGroupClasses, setShowGroupClasses] = useState(true)
  const [eventTypeSelectorOpen, setEventTypeSelectorOpen] = useState(false)
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [classFormModalOpen, setClassFormModalOpen] = useState(false)
  const [detailsModalOpen, setDetailsModalOpen] = useState(false)
  const [classDetailsModalOpen, setClassDetailsModalOpen] = useState(false)
  const [selectedSlot, setSelectedSlot] = useState<{ start: Date; end: Date } | null>(null)
  const [selectedEvent, setSelectedEvent] = useState<Event | null>(null)
  const [editingEvent, setEditingEvent] = useState<Event | null>(null)
  const [selectedClass, setSelectedClass] = useState<ClassOccurrence | null>(null)
  const [editingClass, setEditingClass] = useState<ClassOccurrence | null>(null)
  const [pendingUpdate, setPendingUpdate] = useState<{
    event?: Event
    classOccurrence?: ClassOccurrence
    newStart: Date
    newEnd: Date
    revert: () => void
  } | null>(null)

  // Fetch rooms
  const { data: rooms, isLoading: isLoadingRooms } = useQuery({
    queryKey: roomKeys.list(),
    queryFn: () => roomsApi.list(),
    staleTime: 5 * 60 * 1000, // 5 minutes
  })

  // Fetch events (admin gets all events, staff gets all events to view but can only edit their own)
  const { data: events, isLoading: isLoadingEvents } = useQuery({
    queryKey: isAdmin
      ? eventKeys.allEvents({
          date_from: dateRange.start.toISOString(),
          date_to: dateRange.end.toISOString(),
          room_id: selectedRoomId || undefined,
        })
      : eventKeys.allEventsForStaff({
          date_from: dateRange.start.toISOString(),
          date_to: dateRange.end.toISOString(),
          room_id: selectedRoomId || undefined,
        }),
    queryFn: () => isAdmin
      ? eventsApi.getAllEvents({
          date_from: dateRange.start.toISOString(),
          date_to: dateRange.end.toISOString(),
          room_id: selectedRoomId || undefined,
        })
      : eventsApi.getAllEventsForStaff({
          date_from: dateRange.start.toISOString(),
          date_to: dateRange.end.toISOString(),
          room_id: selectedRoomId || undefined,
        }),
    staleTime: 2 * 60 * 1000, // 2 minutes
  })

  // Fetch group classes
  const { data: groupClasses, isLoading: isLoadingClasses } = useQuery({
    queryKey: classKeys.list({
      date_from: dateRange.start.toISOString(),
      date_to: dateRange.end.toISOString(),
      room_id: selectedRoomId || undefined,
      status: 'scheduled',
    }),
    queryFn: () => classesApi.list({
      date_from: dateRange.start.toISOString(),
      date_to: dateRange.end.toISOString(),
      room_id: selectedRoomId || undefined,
      status: 'scheduled',
    }),
    staleTime: 2 * 60 * 1000, // 2 minutes
    enabled: showGroupClasses,
  })

  const isLoading = isLoadingRooms || isLoadingEvents || isLoadingClasses

  // Update event mutation (for drag & drop)
  const updateMutation = useMutation({
    mutationFn: ({ eventId, data }: { eventId: string; data: { starts_at: string; duration_minutes: number } }) =>
      eventsApi.update(eventId, data),
    onSuccess: async () => {
      // Refetch all event queries immediately to refresh the calendar
      await queryClient.refetchQueries({
        queryKey: eventKeys.all,
        type: 'active'
      })
      toast({ title: t('success.updated') })
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}
      let errorMessage = t('errors.updateFailed')
      if (status === 409) errorMessage = t('errors.conflict')
      else if (status === 422 && data?.message) errorMessage = data.message
      else if (status === 423) errorMessage = t('errors.locked')
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Update class occurrence mutation (for group class drag & drop)
  const updateClassMutation = useMutation({
    mutationFn: ({ occurrenceId, data }: { occurrenceId: string; data: { starts_at: string; ends_at: string } }) =>
      classesApi.updateOccurrence(occurrenceId, data),
    onSuccess: async () => {
      // Refetch all class list queries immediately to refresh the calendar
      await queryClient.refetchQueries({
        queryKey: classKeys.all,
        type: 'active'
      })
      toast({ title: t('success.updated') })
    },
    onError: (error: AxiosError<ApiError>) => {
      const { status, data } = error.response ?? {}
      let errorMessage = t('errors.updateFailed')
      if (status === 409) errorMessage = t('errors.conflict')
      else if (status === 422 && data?.message) errorMessage = data.message
      else if (status === 423) errorMessage = t('errors.locked')
      toast({ variant: 'destructive', title: t('common.error'), description: errorMessage })
    },
  })

  // Helper to get room color from room ID
  const getRoomColor = (roomId: number | string | null | undefined): string => {
    if (!roomId || !rooms) {
      console.log('getRoomColor: no roomId or rooms', { roomId, roomsCount: rooms?.length })
      return '#3b82f6' // default blue
    }
    const room = rooms.find(r => String(r.id) === String(roomId))
    console.log('getRoomColor:', { roomId, room, color: room?.color })
    return room?.color || '#3b82f6'
  }

  // Map events to FullCalendar format
  // Personal training events (INDIVIDUAL, BLOCK) - use room color
  const individualEvents = events?.map(event => {
    const canEdit = isEventOwner(event)
    const roomColor = getRoomColor(event.room_id)
    return {
      id: `event-${event.id}`,
      title: getEventTitle(event),
      start: event.starts_at,
      end: event.ends_at,
      backgroundColor: canEdit ? roomColor : roomColor + '80', // 50% opacity for non-owned
      borderColor: roomColor,
      editable: canEdit,
      startEditable: canEdit, // Allow drag & drop only for owned events
      durationEditable: canEdit, // Allow resize only for owned events
      extendedProps: { event, isGroupClass: false, isOwner: canEdit },
    }
  }) ?? []

  // Map group classes to FullCalendar format - use room color
  const groupClassEvents = (showGroupClasses && groupClasses) ? groupClasses.map(classOccurrence => {
    const roomColor = getRoomColor(classOccurrence.room_id)
    return {
      id: `class-${classOccurrence.id}`,
      title: classOccurrence.class_template?.title || classOccurrence.class_template?.name || t('event.eventType.GROUP_CLASS'),
      start: classOccurrence.starts_at,
      end: classOccurrence.ends_at,
      backgroundColor: roomColor,
      borderColor: roomColor,
      editable: isAdmin, // Only admins can edit group classes
      startEditable: isAdmin, // Allow drag & drop
      durationEditable: isAdmin, // Allow resize
      extendedProps: { classOccurrence, isGroupClass: true },
    }
  }) : []

  // Combine all events
  const calendarEvents = [...individualEvents, ...groupClassEvents]

  // Get event title based on type
  function getEventTitle(event: Event): string {
    if (event.type === 'INDIVIDUAL' && event.client?.user?.name) {
      return event.client.user.name
    }
    if (event.type === 'BLOCK' && event.notes) {
      return `BLOCK: ${event.notes}`
    }
    return t(`event.eventType.${event.type}`)
  }


  // Handle date/time slot selection
  const handleDateSelect = (selectInfo: DateSelectArg) => {
    setSelectedSlot({ start: selectInfo.start, end: selectInfo.end })
    // Admin users can choose between individual and group class
    if (isAdmin) {
      setEventTypeSelectorOpen(true)
    } else {
      setCreateModalOpen(true)
    }
    selectInfo.view.calendar.unselect() // Clear selection
  }

  // Handle edit class from ClassOccurrenceDetailsModal
  const handleEditClass = () => {
    if (selectedClass) {
      setEditingClass(selectedClass)
      setClassDetailsModalOpen(false)
      setClassFormModalOpen(true)
    }
  }

  // Handle event click
  const handleEventClick = (clickInfo: EventClickArg) => {
    const isGroupClass = clickInfo.event.extendedProps.isGroupClass
    const isOwner = clickInfo.event.extendedProps.isOwner

    if (isGroupClass) {
      const classOccurrence = clickInfo.event.extendedProps.classOccurrence as ClassOccurrence
      setSelectedClass(classOccurrence)
      setClassDetailsModalOpen(true)
    } else {
      // Check if staff owns this event - if not, show toast and don't open details
      if (!isOwner && isStaff) {
        toast({
          variant: 'default',
          title: t('errors.notYourEvent', 'Ez nem a te eseményed'),
          description: t('errors.cannotViewOthersEvents', 'Csak a saját eseményeid adatait tekintheted meg.'),
        })
        return
      }

      const event = clickInfo.event.extendedProps.event as Event
      setSelectedEvent(event)
      setDetailsModalOpen(true)
    }
  }

  // Handle event drag & drop
  const handleEventDrop = (info: EventDropArg) => {
    const isGroupClass = info.event.extendedProps.isGroupClass
    const isOwner = info.event.extendedProps.isOwner
    const oldStart = info.oldEvent.start
    const newStart = info.event.start
    const newEnd = info.event.end

    if (!oldStart || !newStart || !newEnd) {
      info.revert()
      return
    }

    if (isGroupClass) {
      // Group class drag and drop (admin only, no same-day restriction)
      if (!isAdmin) {
        info.revert()
        toast({
          variant: 'destructive',
          title: t('common.error'),
          description: t('errors.adminOnly'),
        })
        return
      }

      const classOccurrence = info.event.extendedProps.classOccurrence as ClassOccurrence
      setPendingUpdate({
        classOccurrence,
        newStart,
        newEnd,
        revert: () => info.revert(),
      })
    } else {
      // Individual event drag and drop (same-day restriction)
      const event = info.event.extendedProps.event as Event

      // Check ownership - staff can only move their own events
      if (!isOwner && isStaff) {
        info.revert()
        toast({
          variant: 'destructive',
          title: t('errors.notYourEvent', 'Ez nem a te eseményed'),
          description: t('errors.cannotModifyOthersEvents', 'Csak a saját eseményeidet módosíthatod.'),
        })
        return
      }

      // Check same-day restriction
      if (!isSameDayMove(oldStart.toISOString(), newStart.toISOString())) {
        info.revert()
        toast({
          variant: 'destructive',
          title: t('errors.notSameDay'),
          description: t('dragDrop.sameDayOnly'),
        })
        return
      }

      setPendingUpdate({
        event,
        newStart,
        newEnd,
        revert: () => info.revert(),
      })
    }
  }

  // Handle event resize
  const handleEventResize = (info: EventResizeDoneArg) => {
    const isGroupClass = info.event.extendedProps.isGroupClass
    const isOwner = info.event.extendedProps.isOwner
    const newStart = info.event.start
    const newEnd = info.event.end

    if (!newStart || !newEnd) {
      info.revert()
      return
    }

    if (isGroupClass) {
      // Group class resize (admin only, no same-day restriction)
      if (!isAdmin) {
        info.revert()
        toast({
          variant: 'destructive',
          title: t('common.error'),
          description: t('errors.adminOnly'),
        })
        return
      }

      const classOccurrence = info.event.extendedProps.classOccurrence as ClassOccurrence
      setPendingUpdate({
        classOccurrence,
        newStart,
        newEnd,
        revert: () => info.revert(),
      })
    } else {
      // Individual event resize (same-day restriction)
      const event = info.event.extendedProps.event as Event
      const oldStart = info.oldEvent.start

      // Check ownership - staff can only resize their own events
      if (!isOwner && isStaff) {
        info.revert()
        toast({
          variant: 'destructive',
          title: t('errors.notYourEvent', 'Ez nem a te eseményed'),
          description: t('errors.cannotModifyOthersEvents', 'Csak a saját eseményeidet módosíthatod.'),
        })
        return
      }

      if (!oldStart || !isSameDayMove(oldStart.toISOString(), newStart.toISOString())) {
        info.revert()
        toast({
          variant: 'destructive',
          title: t('errors.notSameDay'),
          description: t('dragDrop.sameDayOnly'),
        })
        return
      }

      setPendingUpdate({
        event,
        newStart,
        newEnd,
        revert: () => info.revert(),
      })
    }
  }

  // Handle dates set (when user navigates calendar)
  const handleDatesSet = (dateInfo: DatesSetArg) => {
    setDateRange({ start: dateInfo.start, end: dateInfo.end })
  }

  // Handle "today + tomorrow" button click
  const handleTodayTomorrowClick = () => {
    setCurrentView('timeGridTwoDay')
  }

  // Handle "week" view button click
  const handleWeekViewClick = () => {
    setCurrentView('timeGridWeek')
  }

  // Handle "day" view button click
  const handleDayViewClick = () => {
    setCurrentView('timeGridDay')
  }

  // Handle "add event" button click
  const handleAddEventClick = () => {
    // Create default time slot: current time rounded up to next half hour, with 1 hour duration
    const now = new Date()
    const minutes = now.getMinutes()
    const roundedMinutes = minutes < 30 ? 30 : 60
    const start = new Date(now)
    start.setMinutes(roundedMinutes, 0, 0)
    if (roundedMinutes === 60) {
      start.setHours(start.getHours() + 1)
      start.setMinutes(0)
    }
    const end = new Date(start.getTime() + 60 * 60 * 1000) // 1 hour later

    setSelectedSlot({ start, end })

    // Admin users can choose between individual and group class
    if (isAdmin) {
      setEventTypeSelectorOpen(true)
    } else {
      setCreateModalOpen(true)
    }
  }

  // Custom event content renderer to show attendance status icon
  const renderEventContent = (eventInfo: { event: { title: string; extendedProps: { isGroupClass: boolean; event?: Event } }; timeText: string }) => {
    const { event: eventData } = eventInfo.event.extendedProps

    // Calculate combined attendance status for multi-guest events
    // Returns: 'all_attended' | 'all_no_show' | 'mixed' | 'partial' | null
    const getCombinedAttendanceStatus = () => {
      if (!eventData || eventData.type !== 'INDIVIDUAL') return null

      const additionalClients = eventData.additional_clients || eventData.additionalClients || []

      // Collect all attendance statuses
      const statuses: (string | null | undefined)[] = []

      // Main client status
      if (eventData.client) {
        statuses.push(eventData.attendance_status)
      }

      // Additional clients statuses
      for (const client of additionalClients) {
        statuses.push(client.pivot?.attendance_status)
      }

      // If no clients, no status
      if (statuses.length === 0) return null

      // Count statuses
      const attendedCount = statuses.filter(s => s === 'attended').length
      const noShowCount = statuses.filter(s => s === 'no_show').length
      const pendingCount = statuses.filter(s => !s).length
      const totalCount = statuses.length

      // All pending - no icon
      if (pendingCount === totalCount) return null

      // All attended - green check
      if (attendedCount === totalCount) return 'all_attended'

      // All no-show - red X
      if (noShowCount === totalCount) return 'all_no_show'

      // Mixed (some attended, some no-show, possibly some pending) - yellow warning
      // This covers: attended + no_show, attended + pending, no_show + pending, or all three
      return 'mixed'
    }

    const combinedStatus = getCombinedAttendanceStatus()

    // Determine icon and color based on combined status
    const getAttendanceIcon = () => {
      if (!combinedStatus) return null

      switch (combinedStatus) {
        case 'all_attended':
          return {
            icon: <Check size={12} color="white" />,
            bgColor: '#22c55e', // green
            title: t('event.allAttended')
          }
        case 'all_no_show':
          return {
            icon: <X size={12} color="white" />,
            bgColor: '#ef4444', // red
            title: t('event.allNoShow')
          }
        case 'mixed':
          return {
            icon: <AlertTriangle size={12} color="white" />,
            bgColor: '#eab308', // yellow
            title: t('event.mixedAttendance')
          }
        default:
          return null
      }
    }

    const attendanceIcon = getAttendanceIcon()

    return (
      <div className="fc-event-main-frame" style={{ position: 'relative', width: '100%', height: '100%' }}>
        <div className="fc-event-time">{eventInfo.timeText}</div>
        <div className="fc-event-title-container">
          <div className="fc-event-title fc-sticky">{eventInfo.event.title}</div>
        </div>
        {/* Attendance status icon - top left */}
        {attendanceIcon && (
          <div
            style={{
              position: 'absolute',
              top: '2px',
              left: '2px',
              backgroundColor: attendanceIcon.bgColor,
              borderRadius: '50%',
              padding: '2px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
            title={attendanceIcon.title}
          >
            {attendanceIcon.icon}
          </div>
        )}
      </div>
    )
  }

  // Handle confirmation of pending update
  const handleConfirmUpdate = () => {
    if (!pendingUpdate) return

    const { event, classOccurrence, newStart, newEnd } = pendingUpdate

    // Format dates without timezone offset (local time)
    const formatLocalDateTime = (date: Date) => format(date, "yyyy-MM-dd'T'HH:mm:ss")

    if (classOccurrence) {
      // Update group class occurrence
      setPendingUpdate(null)
      updateClassMutation.mutate(
        {
          occurrenceId: String(classOccurrence.id),
          data: {
            starts_at: formatLocalDateTime(newStart),
            ends_at: formatLocalDateTime(newEnd),
          },
        },
        {
          onError: () => {
            if (pendingUpdate.revert) {
              pendingUpdate.revert()
            }
          },
        }
      )
    } else if (event) {
      // Update individual event
      const durationMinutes = Math.round((newEnd.getTime() - newStart.getTime()) / 60000)
      setPendingUpdate(null)

      updateMutation.mutate(
        {
          eventId: event.id,
          data: {
            starts_at: formatLocalDateTime(newStart),
            duration_minutes: durationMinutes,
          },
        },
        {
          onError: () => {
            if (pendingUpdate.revert) {
              pendingUpdate.revert()
            }
          },
        }
      )
    }
  }

  // Handle cancellation of pending update
  const handleCancelUpdate = () => {
    if (pendingUpdate?.revert) {
      pendingUpdate.revert()
    }
    setPendingUpdate(null)
  }

  if (isLoading) {
    return (
      <div className="container py-6 space-y-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-[600px] w-full" />
      </div>
    )
  }

  return (
    <div className="w-full px-2 py-4 sm:container sm:px-4 sm:py-6">
      {/* Header - stacked on mobile, row on desktop */}
      <div className="flex flex-col gap-4 mb-4 sm:mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <h1 className="text-2xl sm:text-3xl font-bold">{t('myCalendar')}</h1>
          <Button onClick={handleAddEventClick} className="w-full sm:w-auto">
            <Plus className="h-4 w-4 mr-2" />
            {t('addEvent')}
          </Button>
        </div>

        {/* Controls - wrapped on mobile */}
        <div className="flex flex-wrap gap-2 items-center">
          {/* Room selector */}
          <Select
            value={selectedRoomId || 'all'}
            onValueChange={(value) => setSelectedRoomId(value === 'all' ? null : value)}
          >
            <SelectTrigger className="w-full sm:w-[180px]">
              <SelectValue placeholder={t('selectRoom')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('allRooms')}</SelectItem>
              {rooms?.map((room) => (
                <SelectItem key={room.id} value={String(room.id)}>
                  {room.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {/* Group classes checkbox */}
          <div className="flex items-center gap-2 px-3 py-2 border rounded-md bg-background">
            <Checkbox
              id="show-group-classes"
              checked={showGroupClasses}
              onCheckedChange={(checked) => setShowGroupClasses(checked === true)}
            />
            <Label
              htmlFor="show-group-classes"
              className="text-sm font-normal cursor-pointer whitespace-nowrap"
            >
              {t('showGroupClasses')}
            </Label>
          </div>

          {/* View buttons */}
          <div className="flex gap-1 sm:gap-2 ml-auto">
            <Button variant="outline" size="sm" onClick={handleTodayTomorrowClick} className="text-xs sm:text-sm px-2 sm:px-3">
              {t('todayTomorrow')}
            </Button>
            <Button variant="outline" size="sm" onClick={handleWeekViewClick} className="text-xs sm:text-sm px-2 sm:px-3">
              {t('weekView')}
            </Button>
            <Button variant="outline" size="sm" onClick={handleDayViewClick} className="text-xs sm:text-sm px-2 sm:px-3">
              {t('dayView')}
            </Button>
          </div>
        </div>
      </div>

      {/* Calendar container - full width on mobile */}
      <div className="bg-card rounded-lg border p-1 sm:p-4 -mx-2 sm:mx-0 overflow-x-auto">
        <FullCalendar
          ref={calendarRef}
          plugins={[timeGridPlugin, interactionPlugin]}
          initialView={currentView}
          key={currentView}
          views={{
            timeGridTwoDay: {
              type: 'timeGrid',
              duration: { days: 2 },
              buttonText: t('todayTomorrow'),
            },
          }}
          headerToolbar={{
            left: 'prev,next',
            center: 'title',
            right: '',
          }}
          slotMinTime="06:00:00"
          slotMaxTime="22:00:00"
          slotDuration="00:30:00"
          allDaySlot={false}
          editable={true}
          selectable={true}
          selectMirror={true}
          longPressDelay={300}
          selectLongPressDelay={300}
          eventLongPressDelay={300}
          dayMaxEvents={true}
          weekends={true}
          height="auto"
          locale={i18n.language}
          firstDay={1} // Monday
          events={calendarEvents}
          eventContent={renderEventContent}
          select={handleDateSelect}
          eventClick={handleEventClick}
          eventDrop={handleEventDrop}
          eventResize={handleEventResize}
          datesSet={handleDatesSet}
          buttonText={{
            today: t('today'),
            week: t('weekView'),
            day: t('dayView'),
          }}
          slotLabelFormat={{
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
          }}
          eventTimeFormat={{
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
          }}
        />
      </div>

      <EventFormModal
        open={createModalOpen}
        onOpenChange={(open) => {
          setCreateModalOpen(open)
          if (!open) {
            setEditingEvent(null)
          }
        }}
        initialData={selectedSlot ? {
          starts_at: selectedSlot.start.toISOString(),
          duration_minutes: Math.max(60, Math.round((selectedSlot.end.getTime() - selectedSlot.start.getTime()) / 60000)),
        } : undefined}
        editingEvent={editingEvent}
        isAdmin={isAdmin}
        onSuccess={() => {
          setSelectedSlot(null)
          setEditingEvent(null)
        }}
      />

      {selectedEvent && (
        <EventDetailsModal
          event={selectedEvent}
          open={detailsModalOpen}
          onOpenChange={setDetailsModalOpen}
          onEventUpdated={() => {
            queryClient.refetchQueries({
              queryKey: eventKeys.all,
              type: 'active'
            })
          }}
          isAdmin={isAdmin}
          canEdit={isEventOwner(selectedEvent)}
          onEdit={(event) => {
            setEditingEvent(event)
            setDetailsModalOpen(false)
            setCreateModalOpen(true)
          }}
        />
      )}

      {selectedClass && (
        <ClassOccurrenceDetailsModal
          classOccurrence={selectedClass}
          open={classDetailsModalOpen}
          onOpenChange={setClassDetailsModalOpen}
          onEdit={handleEditClass}
        />
      )}

      {/* Event Type Selector (admin only) */}
      <EventTypeSelectorModal
        open={eventTypeSelectorOpen}
        onOpenChange={setEventTypeSelectorOpen}
        onSelectIndividual={() => setCreateModalOpen(true)}
        onSelectGroupClass={() => setClassFormModalOpen(true)}
      />

      {/* Class Occurrence Form Modal (admin only) */}
      <ClassOccurrenceFormModal
        open={classFormModalOpen}
        onOpenChange={(open) => {
          setClassFormModalOpen(open)
          if (!open) {
            setEditingClass(null)
            setSelectedSlot(null)
          }
        }}
        initialData={selectedSlot ? {
          starts_at: selectedSlot.start.toISOString(),
          duration_minutes: Math.max(60, Math.round((selectedSlot.end.getTime() - selectedSlot.start.getTime()) / 60000)),
        } : undefined}
        editingOccurrence={editingClass}
        onSuccess={() => {
          setSelectedSlot(null)
          setEditingClass(null)
          queryClient.invalidateQueries({ queryKey: classKeys.lists() })
        }}
      />

      {/* Event Update Confirmation Dialog */}
      <AlertDialog open={!!pendingUpdate} onOpenChange={(open) => !open && handleCancelUpdate()}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('dragDrop.confirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {pendingUpdate && (
                <>
                  {t('dragDrop.confirmMessage')}
                  <br /><br />
                  <strong>{t('form.startTime')}:</strong> {format(pendingUpdate.newStart, 'PPp', { locale: i18n.language === 'hu' ? hu : enUS })}
                  <br />
                  <strong>{t('form.endTime')}:</strong> {format(pendingUpdate.newEnd, 'PPp', { locale: i18n.language === 'hu' ? hu : enUS })}
                </>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={handleCancelUpdate}>
              {t('actions.cancel')}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmUpdate}
              disabled={updateMutation.isPending || updateClassMutation.isPending}
            >
              {(updateMutation.isPending || updateClassMutation.isPending) ? t('common:loading') : t('dragDrop.confirmSave')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
