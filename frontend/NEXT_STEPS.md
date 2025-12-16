# FunctionalFit Calendar Frontend - Next Steps Guide

## Quick Start for Developers

This guide helps you start implementing UI features using the complete foundation that's already built.

---

## What's Already Done

✅ **TypeScript API Types** - All backend models typed (`class.ts`, `event.ts`, `client.ts`, `staff.ts`)
✅ **API Client Functions** - All endpoints implemented with React Query keys
✅ **i18n Translations** - Full HU/EN translations for all features
✅ **Zod Validation** - Form schemas for booking and events
✅ **shadcn/ui Components** - Dialog, Select, Badge, Calendar, AlertDialog, Separator, Skeleton
✅ **Axios Client** - Configured with error handling (409, 422, 423, 401, 403)
✅ **React Query** - Configured with cache management
✅ **React Router** - Routes configured with ProtectedRoute guard
✅ **Auth Hook** - useAuth() ready to use

---

## Implementation Priority Order

### 1. Class Booking Flow (HIGH PRIORITY - Client Feature)

**Files to create:**
- `frontend/src/pages/classes/ClassesPage.tsx` - Main class browsing page
- `frontend/src/components/classes/ClassCard.tsx` - Individual class card
- `frontend/src/components/classes/ClassDetailsModal.tsx` - Class details with book/cancel
- `frontend/src/components/classes/ClassFilters.tsx` - Filter controls
- `frontend/src/pages/classes/MyBookingsPage.tsx` - My bookings list

**Step-by-step implementation:**

#### Step 1.1: ClassesPage with List

```tsx
// frontend/src/pages/classes/ClassesPage.tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { classesApi, classKeys } from '@/api/classes'
import { ClassListFilters } from '@/types/class'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

export default function ClassesPage() {
  const { t } = useTranslation('classes')
  const [filters, setFilters] = useState<ClassListFilters>({
    has_capacity: true,
    status: 'scheduled',
  })

  const { data: classes, isLoading, error } = useQuery({
    queryKey: classKeys.list(filters),
    queryFn: () => classesApi.list(filters),
    staleTime: 2 * 60 * 1000, // 2 minutes
  })

  if (isLoading) {
    return <div className="space-y-4">
      {[1, 2, 3].map(i => <Skeleton key={i} className="h-32 w-full" />)}
    </div>
  }

  if (error) {
    return <div className="text-destructive">{t('errors.loadFailed')}</div>
  }

  return (
    <div className="container py-6">
      <h1 className="text-3xl font-bold mb-6">{t('title')}</h1>

      {/* TODO: Add ClassFilters component here */}

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {classes?.map(classOccurrence => (
          <ClassCard key={classOccurrence.id} classOccurrence={classOccurrence} />
        ))}
      </div>

      {classes?.length === 0 && (
        <div className="text-center text-muted-foreground py-12">
          {t('common.noData')}
        </div>
      )}
    </div>
  )
}
```

#### Step 1.2: ClassCard Component

```tsx
// frontend/src/components/classes/ClassCard.tsx
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

  const spotsLeft = (classOccurrence.capacity_override ?? classOccurrence.class_template?.capacity ?? 0) - (classOccurrence.registered_count ?? 0)
  const isFull = spotsLeft <= 0

  return (
    <>
      <Card className="hover:shadow-lg transition-shadow cursor-pointer" onClick={() => setDetailsOpen(true)}>
        <CardHeader>
          <div className="flex justify-between items-start">
            <CardTitle>{classOccurrence.class_template?.name}</CardTitle>
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
              <span>{classOccurrence.staff?.user?.name ?? '-'}</span>
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
          <Button className="w-full" disabled={isFull}>
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
```

#### Step 1.3: ClassDetailsModal with Booking

```tsx
// frontend/src/components/classes/ClassDetailsModal.tsx
import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { ClassOccurrence, BookClassFormData } from '@/types/class'
import { classesApi, classKeys } from '@/api/classes'
import { bookClassSchema } from '@/lib/validations/booking'
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
  const { t } = useTranslation('classes')
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

      const message = response.status === 'confirmed'
        ? t('booking.successConfirmed')
        : t('booking.successWaitlist')

      toast({
        title: t('common.success'),
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
      }

      toast({
        variant: 'destructive',
        title: t('common.error'),
        description: errorMessage,
      })
    },
  })

  const handleBook = form.handleSubmit((data) => {
    bookMutation.mutate(data)
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{classOccurrence.class_template?.name}</DialogTitle>
          <DialogDescription>
            {classOccurrence.class_template?.description}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Class details display */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label className="text-muted-foreground">{t('trainer')}</Label>
              <div>{classOccurrence.staff?.user?.name ?? '-'}</div>
            </div>
            <div>
              <Label className="text-muted-foreground">{t('room')}</Label>
              <div>{classOccurrence.room?.name}</div>
            </div>
            <div>
              <Label className="text-muted-foreground">{t('capacity')}</Label>
              <div>{classOccurrence.registered_count} / {classOccurrence.capacity_override ?? classOccurrence.class_template?.capacity}</div>
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
                {t('common.close')}
              </Button>
              <Button onClick={() => setShowBookingForm(true)}>
                {classOccurrence.has_capacity ? t('bookNow') : t('joinWaitlist')}
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
                {t('common.cancel')}
              </Button>
              <Button onClick={handleBook} disabled={bookMutation.isPending}>
                {bookMutation.isPending ? t('common.loading') : t('common.submit')}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
```

---

### 2. Calendar UI (HIGH PRIORITY - Staff Feature)

**Files to create:**
- `frontend/src/pages/calendar/CalendarPage.tsx` - Main calendar with week/day/list views
- `frontend/src/components/calendar/CalendarHeader.tsx` - View toggle + navigation
- `frontend/src/components/calendar/EventFormModal.tsx` - Create/edit event form
- `frontend/src/components/calendar/EventDetailsModal.tsx` - View event details
- `frontend/src/components/calendar/ClientPicker.tsx` - Searchable client select

**Required library:**
```bash
npm install @fullcalendar/react @fullcalendar/daygrid @fullcalendar/timegrid @fullcalendar/interaction
```

**Step-by-step implementation:**

#### Step 2.1: CalendarPage with FullCalendar

```tsx
// frontend/src/pages/calendar/CalendarPage.tsx
import { useState, useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import FullCalendar from '@fullcalendar/react'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import { eventsApi, eventKeys } from '@/api/events'
import { isSameDayMove } from '@/lib/validations/event'
import { useToast } from '@/hooks/use-toast'
import { EventFormModal } from '@/components/calendar/EventFormModal'

export default function CalendarPage() {
  const { t } = useTranslation('calendar')
  const { toast } = useToast()
  const calendarRef = useRef<FullCalendar>(null)
  const [dateRange, setDateRange] = useState({
    start: new Date(),
    end: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000), // 7 days ahead
  })
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [selectedSlot, setSelectedSlot] = useState<{ start: Date; end: Date } | null>(null)

  const { data: events, isLoading } = useQuery({
    queryKey: eventKeys.myEvents({
      date_from: dateRange.start.toISOString(),
      date_to: dateRange.end.toISOString(),
    }),
    queryFn: () => eventsApi.getMyEvents({
      date_from: dateRange.start.toISOString(),
      date_to: dateRange.end.toISOString(),
    }),
  })

  const calendarEvents = events?.map(event => ({
    id: event.id,
    title: event.client?.user?.name ?? t('event.eventType.' + event.type),
    start: event.starts_at,
    end: event.ends_at,
    backgroundColor: event.type === 'INDIVIDUAL' ? '#3b82f6' : event.type === 'BLOCK' ? '#6b7280' : '#10b981',
    extendedProps: { event },
  })) ?? []

  const handleDateSelect = (selectInfo: any) => {
    setSelectedSlot({ start: selectInfo.start, end: selectInfo.end })
    setCreateModalOpen(true)
  }

  const handleEventDrop = (info: any) => {
    const oldStart = info.oldEvent.start
    const newStart = info.event.start

    if (!isSameDayMove(oldStart.toISOString(), newStart.toISOString())) {
      info.revert()
      toast({
        variant: 'destructive',
        title: t('errors.notSameDay'),
        description: t('dragDrop.sameDayOnly'),
      })
      return
    }

    // TODO: Implement optimistic update with eventsApi.update()
    toast({
      title: t('success.movedSameDay'),
    })
  }

  return (
    <div className="container py-6">
      <h1 className="text-3xl font-bold mb-6">{t('myCalendar')}</h1>

      <FullCalendar
        ref={calendarRef}
        plugins={[timeGridPlugin, interactionPlugin]}
        initialView="timeGridWeek"
        headerToolbar={{
          left: 'prev,next today',
          center: 'title',
          right: 'timeGridWeek,timeGridDay',
        }}
        slotMinTime="06:00:00"
        slotMaxTime="22:00:00"
        slotDuration="00:30:00"
        allDaySlot={false}
        editable={true}
        selectable={true}
        selectMirror={true}
        dayMaxEvents={true}
        weekends={true}
        events={calendarEvents}
        select={handleDateSelect}
        eventDrop={handleEventDrop}
        eventResize={handleEventDrop}
        loading={isLoading}
        datesSet={(dateInfo) => {
          setDateRange({ start: dateInfo.start, end: dateInfo.end })
        }}
      />

      <EventFormModal
        open={createModalOpen}
        onOpenChange={setCreateModalOpen}
        initialData={selectedSlot ? {
          starts_at: selectedSlot.start.toISOString(),
          duration_minutes: Math.round((selectedSlot.end.getTime() - selectedSlot.start.getTime()) / 60000),
        } : undefined}
      />
    </div>
  )
}
```

---

### 3. Client Portal (MEDIUM PRIORITY)

**Files to create:**
- `frontend/src/pages/client/ClientActivityPage.tsx` - Activity history
- `frontend/src/pages/client/ClientPassesPage.tsx` - Active/expired passes
- `frontend/src/components/client/ActivityTable.tsx` - Activity list table
- `frontend/src/components/client/PassCard.tsx` - Pass details card

**Implementation is similar to Classes and Calendar - use the API client functions and React Query hooks.**

---

### 4. Staff Dashboard (MEDIUM PRIORITY)

**Files to create:**
- `frontend/src/pages/staff/StaffDashboardPage.tsx` - Today's schedule + stats
- `frontend/src/pages/staff/StaffExportsPage.tsx` - Payout/attendance exports
- `frontend/src/components/staff/TodaySchedule.tsx` - Today's events list
- `frontend/src/components/staff/StatsCards.tsx` - Summary stats

---

## Common Patterns

### 1. Loading States with Skeleton

```tsx
if (isLoading) {
  return (
    <div className="space-y-4">
      {[1, 2, 3].map(i => (
        <Skeleton key={i} className="h-24 w-full" />
      ))}
    </div>
  )
}
```

### 2. Error Handling with Toast

```tsx
const mutation = useMutation({
  mutationFn: apiFunction,
  onError: (error: AxiosError<ApiError>) => {
    const { status, data } = error.response ?? {}

    let errorMessage = t('errors.genericError')

    if (status === 409) {
      errorMessage = t('errors.conflict')
    } else if (status === 422 && data?.message) {
      errorMessage = data.message
    } else if (status === 423) {
      errorMessage = t('errors.locked')
    }

    toast({
      variant: 'destructive',
      title: t('common.error'),
      description: errorMessage,
    })
  },
})
```

### 3. Optimistic Updates

```tsx
const mutation = useMutation({
  mutationFn: eventsApi.update,
  onMutate: async (updatedEvent) => {
    await queryClient.cancelQueries({ queryKey: eventKeys.myEvents() })

    const previousEvents = queryClient.getQueryData(eventKeys.myEvents())

    queryClient.setQueryData(eventKeys.myEvents(), (old: Event[]) =>
      old.map(event => event.id === updatedEvent.id ? { ...event, ...updatedEvent } : event)
    )

    return { previousEvents }
  },
  onError: (err, variables, context) => {
    queryClient.setQueryData(eventKeys.myEvents(), context?.previousEvents)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: eventKeys.myEvents() })
  },
})
```

### 4. Form with Zod Validation

```tsx
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { createEventSchema, CreateEventFormData } from '@/lib/validations/event'

const form = useForm<CreateEventFormData>({
  resolver: zodResolver(createEventSchema),
  defaultValues: {
    type: 'INDIVIDUAL',
    duration_minutes: 60,
  },
})

const onSubmit = form.handleSubmit((data) => {
  mutation.mutate(data)
})
```

### 5. Translations with Pluralization

```tsx
const { t } = useTranslation('classes')

// Simple
t('title')  // "Órarend" or "Class Schedule"

// With interpolation
t('booking.creditDeducted', { count: 2 })  // "2 kredit levonva" or "2 credits deducted"

// With pluralization
t('spotsLeft', { count: spotsLeft })
// count=0: "Nincs szabad hely" / "No spots left"
// count=1: "1 szabad hely" / "1 spot left"
// count>1: "5 szabad hely" / "5 spots left"
```

---

## Useful Commands

### Development Server

```bash
cd frontend
npm run dev
# Opens on http://localhost:3000
# API proxied to http://localhost:8080
```

### Type Checking

```bash
npm run type-check
# Runs TypeScript compiler without emitting files
```

### Build

```bash
npm run build
# Compiles TypeScript and builds for production
```

---

## Tips & Best Practices

1. **Always use React Query for API calls** - Never use `useEffect` + `fetch`
2. **Use translation keys** - Never hardcode strings
3. **Add data-testid attributes** - For E2E tests (e.g., `data-testid="book-class-btn"`)
4. **Use Zod schemas** - For all forms, infer types with `z.infer<typeof schema>`
5. **Handle loading states** - Use `Skeleton` components
6. **Handle error states** - Use `toast` for user feedback
7. **Use optimistic updates** - For better UX (mutations appear instant)
8. **Invalidate queries** - After mutations to refresh data
9. **Follow the file structure** - Components in `/components/{feature}/`, pages in `/pages/{feature}/`
10. **Check existing components** - Use shadcn/ui components from `/components/ui/`

---

## Questions?

- Check `IMPLEMENTATION_SUMMARY.md` for detailed architecture
- Check `frontend/src/api/` for API client examples
- Check `frontend/src/types/` for TypeScript types
- Check `frontend/public/locales/` for translation keys
- Check `frontend/src/lib/validations/` for Zod schemas

Happy coding! 🚀
