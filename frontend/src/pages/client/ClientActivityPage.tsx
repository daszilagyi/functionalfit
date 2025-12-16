import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '@/hooks/useAuth'
import { clientsApi, clientKeys } from '@/api/clients'
import { classesApi } from '@/api/classes'
import type { UpcomingBooking, ActivityHistoryFilters } from '@/types/client'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Progress } from '@/components/ui/progress'
import { Input } from '@/components/ui/input'
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
import { Calendar, CreditCard, Clock, TrendingUp, CheckCircle2, XCircle, AlertCircle, X, Filter } from 'lucide-react'
import { format, parseISO, isPast, differenceInHours } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'
import { useToast } from '@/hooks/use-toast'

export default function ClientActivityPage() {
  const { t, i18n } = useTranslation('client')
  const { user } = useAuth()
  const { toast } = useToast()
  const queryClient = useQueryClient()
  const [activeTab, setActiveTab] = useState('upcoming')
  const [cancelBookingId, setCancelBookingId] = useState<string | null>(null)

  // Activity filters state
  const [filters, setFilters] = useState<ActivityHistoryFilters>({})
  const [showFilters, setShowFilters] = useState(false)

  const locale = i18n.language === 'hu' ? hu : enUS

  // Get client ID from user's client profile
  const clientId = user?.client?.id ? String(user.client.id) : ''

  // Fetch upcoming bookings
  const { data: upcoming, isLoading: upcomingLoading } = useQuery({
    queryKey: clientKeys.upcoming(clientId),
    queryFn: () => clientsApi.getUpcoming(clientId),
    enabled: !!clientId,
  })

  // Fetch activity history with filters
  const { data: activity, isLoading: activityLoading } = useQuery({
    queryKey: clientKeys.activity(clientId, filters),
    queryFn: () => clientsApi.getActivity(clientId, filters),
    enabled: !!clientId,
  })

  // Cancel booking mutation
  const cancelMutation = useMutation({
    mutationFn: (occurrenceId: string) => classesApi.cancel(occurrenceId),
    onSuccess: () => {
      toast({
        title: t('common:success'),
        description: t('upcoming.cancelSuccess'),
      })
      // Invalidate queries to refresh data
      queryClient.invalidateQueries({ queryKey: clientKeys.upcoming(clientId) })
      queryClient.invalidateQueries({ queryKey: clientKeys.activity(clientId) })
      queryClient.invalidateQueries({ queryKey: clientKeys.passes(clientId) })
      setCancelBookingId(null)
    },
    onError: (error: any) => {
      const message =
        error.response?.status === 423
          ? t('upcoming.cannotCancelTooLate')
          : error.response?.data?.message || t('upcoming.cancelFailed')
      toast({
        title: t('common:error'),
        description: message,
        variant: 'destructive',
      })
      setCancelBookingId(null)
    },
  })

  // Fetch passes
  const { data: passes, isLoading: passesLoading } = useQuery({
    queryKey: clientKeys.passes(clientId),
    queryFn: () => clientsApi.getPasses(clientId),
    enabled: !!clientId,
  })

  const formatDateTime = (dateStr: string) => {
    try {
      return format(parseISO(dateStr), 'yyyy. MMM d. HH:mm', { locale })
    } catch {
      return dateStr
    }
  }

  const formatDate = (dateStr: string) => {
    try {
      return format(parseISO(dateStr), 'yyyy. MMMM d.', { locale })
    } catch {
      return dateStr
    }
  }

  const getPassStatusBadge = (status: string) => {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline', label: string }> = {
      active: { variant: 'default', label: t('passes.passDetails.status.active') },
      expired: { variant: 'secondary', label: t('passes.passDetails.status.expired') },
      depleted: { variant: 'destructive', label: t('passes.passDetails.status.depleted') },
    }
    const config = variants[status] || variants.active
    return <Badge variant={config.variant}>{config.label}</Badge>
  }

  const canCancelBooking = (booking: UpcomingBooking) => {
    if (!booking) return false
    try {
      const deadline = parseISO(booking.cancellation_deadline || booking.starts_at)
      const hoursUntil = differenceInHours(deadline, new Date())
      return hoursUntil >= 24 && booking.can_cancel
    } catch {
      return booking.can_cancel
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('client:title')}</h1>
        <p className="text-gray-500 mt-2">
          {t('client:myActivity')} - {user?.name}
        </p>
      </div>

      {/* Summary Stats */}
      {activity && (
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">
                {t('activity.totalSessions')}
              </CardTitle>
              <Calendar className="h-4 w-4 text-blue-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{activity.summary.total_sessions}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">
                {t('activity.attendedSessions')}
              </CardTitle>
              <CheckCircle2 className="h-4 w-4 text-green-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{activity.summary.attended_sessions}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">
                {t('activity.attendanceRate')}
              </CardTitle>
              <TrendingUp className="h-4 w-4 text-purple-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {activity.summary?.attendance_rate?.toFixed(0) ?? '0'}%
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">
                {t('activity.totalCreditsUsed')}
              </CardTitle>
              <CreditCard className="h-4 w-4 text-orange-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{activity.summary.total_credits_used}</div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Tabs for different views */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList>
          <TabsTrigger value="upcoming">{t('upcoming.title')}</TabsTrigger>
          <TabsTrigger value="passes">{t('passes.title')}</TabsTrigger>
          <TabsTrigger value="activity">{t('activity.title')}</TabsTrigger>
        </TabsList>

        {/* Upcoming Bookings Tab */}
        <TabsContent value="upcoming" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{t('upcoming.title')}</CardTitle>
              <CardDescription>
                {upcoming?.length || 0} közelgő foglalás
              </CardDescription>
            </CardHeader>
            <CardContent>
              {upcomingLoading ? (
                <div className="space-y-3">
                  {[1, 2, 3].map((i) => (
                    <Skeleton key={i} className="h-20 w-full" />
                  ))}
                </div>
              ) : upcoming && upcoming.length > 0 ? (
                <div className="space-y-3">
                  {upcoming.map((booking) => (
                    <div
                      key={booking.id}
                      className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                    >
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <h3 className="font-medium">{booking.title}</h3>
                          <Badge variant="outline">
                            {booking.type === 'class' ? t('activity.item.class') : t('activity.item.event')}
                          </Badge>
                        </div>
                        <div className="flex items-center gap-4 mt-1 text-sm text-gray-500">
                          <div className="flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {formatDateTime(booking.starts_at)}
                          </div>
                          {booking.room && <span>• {booking.room}</span>}
                          {booking.trainer && <span>• {booking.trainer}</span>}
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        {canCancelBooking(booking) ? (
                          <>
                            <Badge variant="outline" className="text-green-600 border-green-600">
                              {t('upcoming.canCancel')}
                            </Badge>
                            <Button
                              variant="destructive"
                              size="sm"
                              onClick={() => setCancelBookingId(booking.type === 'class' ? booking.occurrence_id || booking.id : booking.id)}
                              disabled={cancelMutation.isPending}
                            >
                              <X className="h-4 w-4 mr-1" />
                              {t('common:cancel')}
                            </Button>
                          </>
                        ) : (
                          <Badge variant="outline" className="text-red-600 border-red-600">
                            <AlertCircle className="h-3 w-3 mr-1" />
                            {t('upcoming.cannotCancel')}
                          </Badge>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-8">
                  Nincs közelgő foglalás
                </p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Passes Tab */}
        <TabsContent value="passes" className="space-y-4">
          {/* Active Passes */}
          <Card>
            <CardHeader>
              <CardTitle>{t('passes.activePasses')}</CardTitle>
              <CardDescription>
                {t('passes.totalCreditsRemaining')}: {passes?.total_credits_remaining || 0}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {passesLoading ? (
                <div className="space-y-3">
                  {[1, 2].map((i) => (
                    <Skeleton key={i} className="h-32 w-full" />
                  ))}
                </div>
              ) : passes && passes.active_passes.length > 0 ? (
                <div className="grid gap-4 md:grid-cols-2">
                  {passes.active_passes.map((pass) => (
                    <Card key={pass.id} className="border-2 border-blue-200">
                      <CardHeader>
                        <div className="flex items-center justify-between">
                          <CardTitle className="text-lg">{pass.pass_type}</CardTitle>
                          {getPassStatusBadge(pass.status)}
                        </div>
                      </CardHeader>
                      <CardContent className="space-y-3">
                        <div className="flex justify-between text-sm">
                          <span className="text-gray-500">{t('passes.passDetails.creditsRemaining')}:</span>
                          <span className="font-semibold">
                            {pass.credits_remaining === null
                              ? t('passes.passDetails.unlimited')
                              : `${pass.credits_remaining} / ${pass.credits_total}`}
                          </span>
                        </div>
                        {/* Progress bar for credit-based passes */}
                        {pass.credits_total !== null && pass.credits_remaining !== null && (
                          <div className="space-y-1">
                            <Progress
                              value={(pass.credits_remaining / pass.credits_total) * 100}
                              className="h-2"
                            />
                            <p className="text-xs text-gray-500 text-right">
                              {Math.round((pass.credits_remaining / pass.credits_total) * 100)}% {t('passes.passDetails.remaining')}
                            </p>
                          </div>
                        )}
                        <div className="flex justify-between text-sm">
                          <span className="text-gray-500">{t('passes.passDetails.purchasedAt')}:</span>
                          <span>{formatDate(pass.purchased_at)}</span>
                        </div>
                        {pass.expires_at && (
                          <div className="flex justify-between text-sm">
                            <span className="text-gray-500">{t('passes.passDetails.expiresAt')}:</span>
                            <span className={isPast(parseISO(pass.expires_at)) ? 'text-red-600 font-medium' : ''}>
                              {formatDate(pass.expires_at)}
                            </span>
                          </div>
                        )}
                      </CardContent>
                    </Card>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-8">
                  {t('passes.noActivePasses')}
                </p>
              )}
            </CardContent>
          </Card>

          {/* Expired Passes */}
          {passes && passes.expired_passes.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t('passes.expiredPasses')}</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid gap-4 md:grid-cols-2">
                  {passes.expired_passes.map((pass) => (
                    <Card key={pass.id} className="border opacity-60">
                      <CardHeader>
                        <div className="flex items-center justify-between">
                          <CardTitle className="text-lg">{pass.pass_type}</CardTitle>
                          {getPassStatusBadge(pass.status)}
                        </div>
                      </CardHeader>
                      <CardContent className="space-y-2 text-sm">
                        <div className="flex justify-between">
                          <span className="text-gray-500">{t('passes.passDetails.purchasedAt')}:</span>
                          <span>{formatDate(pass.purchased_at)}</span>
                        </div>
                        {pass.expires_at && (
                          <div className="flex justify-between">
                            <span className="text-gray-500">{t('passes.passDetails.expiresAt')}:</span>
                            <span className="text-red-600">{formatDate(pass.expires_at)}</span>
                          </div>
                        )}
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* Activity History Tab */}
        <TabsContent value="activity" className="space-y-4">
          {/* Filters */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>{t('activity.filters.title')}</CardTitle>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setShowFilters(!showFilters)}
                >
                  <Filter className="h-4 w-4 mr-2" />
                  {showFilters ? t('common:hideFilters') : t('common:showFilters')}
                </Button>
              </div>
            </CardHeader>
            {showFilters && (
              <CardContent className="space-y-4">
                <div className="grid gap-4 md:grid-cols-4">
                  {/* Date From */}
                  <div className="space-y-2">
                    <Label htmlFor="date-from">{t('activity.filters.dateFrom')}</Label>
                    <Input
                      id="date-from"
                      type="date"
                      value={filters.date_from || ''}
                      onChange={(e) =>
                        setFilters((prev) => ({ ...prev, date_from: e.target.value || undefined }))
                      }
                    />
                  </div>
                  {/* Date To */}
                  <div className="space-y-2">
                    <Label htmlFor="date-to">{t('activity.filters.dateTo')}</Label>
                    <Input
                      id="date-to"
                      type="date"
                      value={filters.date_to || ''}
                      onChange={(e) =>
                        setFilters((prev) => ({ ...prev, date_to: e.target.value || undefined }))
                      }
                    />
                  </div>
                  {/* Type Filter */}
                  <div className="space-y-2">
                    <Label htmlFor="type-filter">{t('activity.filters.type')}</Label>
                    <select
                      id="type-filter"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      value={filters.type || ''}
                      onChange={(e) =>
                        setFilters((prev) => ({
                          ...prev,
                          type: e.target.value ? (e.target.value as 'class' | 'event') : undefined,
                        }))
                      }
                    >
                      <option value="">{t('activity.filters.all')}</option>
                      <option value="class">{t('activity.filters.classes')}</option>
                      <option value="event">{t('activity.filters.events')}</option>
                    </select>
                  </div>
                  {/* Attendance Filter */}
                  <div className="space-y-2">
                    <Label htmlFor="attended-filter">{t('activity.filters.attendance')}</Label>
                    <select
                      id="attended-filter"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      value={filters.attended === null ? 'all' : filters.attended?.toString() || 'all'}
                      onChange={(e) =>
                        setFilters((prev) => ({
                          ...prev,
                          attended:
                            e.target.value === 'all' ? undefined : e.target.value === 'true',
                        }))
                      }
                    >
                      <option value="all">{t('activity.filters.all')}</option>
                      <option value="true">{t('activity.filters.attended')}</option>
                      <option value="false">{t('activity.filters.missed')}</option>
                    </select>
                  </div>
                </div>
                {/* Clear Filters Button */}
                <div className="flex justify-end">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setFilters({})}
                    disabled={Object.keys(filters).length === 0}
                  >
                    <X className="h-4 w-4 mr-2" />
                    {t('common:clearFilters')}
                  </Button>
                </div>
              </CardContent>
            )}
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t('activity.title')}</CardTitle>
              <CardDescription>
                {activity?.activities.length || 0} {t('activity.results')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {activityLoading ? (
                <div className="space-y-3">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Skeleton key={i} className="h-16 w-full" />
                  ))}
                </div>
              ) : activity && activity.activities.length > 0 ? (
                <div className="space-y-2">
                  {activity.activities.map((item) => (
                    <div
                      key={item.id}
                      className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50"
                    >
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <h4 className="font-medium">{item.title}</h4>
                          <Badge variant="outline">
                            {item.type === 'class' ? t('activity.item.class') : t('activity.item.event')}
                          </Badge>
                          {item.attended === true && (
                            <Badge variant="default" className="bg-green-600">
                              <CheckCircle2 className="h-3 w-3 mr-1" />
                              {t('activity.item.attended')}
                            </Badge>
                          )}
                          {item.attended === false && (
                            <Badge variant="destructive">
                              <XCircle className="h-3 w-3 mr-1" />
                              {t('activity.item.noShow')}
                            </Badge>
                          )}
                        </div>
                        <div className="flex items-center gap-4 mt-1 text-sm text-gray-500">
                          <span>{formatDate(item.date)}</span>
                          <span>
                            {item.start_time} - {item.end_time}
                          </span>
                          {item.room && <span>• {item.room}</span>}
                          {item.trainer && <span>• {item.trainer}</span>}
                        </div>
                      </div>
                      <div className="text-right text-sm">
                        <div className="font-medium">{t('activity.item.creditsUsed', { count: item.credits_used })}</div>
                        {item.checked_in_at && (
                          <div className="text-xs text-gray-500 mt-1">
                            Check-in: {formatDateTime(item.checked_in_at)}
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-8">
                  {t('activity.emptyState')}
                </p>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Cancel Booking Confirmation Dialog */}
      <AlertDialog open={!!cancelBookingId} onOpenChange={(open) => !open && setCancelBookingId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('upcoming.cancelConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('upcoming.cancelConfirmMessage')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancelMutation.isPending}>
              {t('common:cancel')}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => cancelBookingId && cancelMutation.mutate(cancelBookingId)}
              disabled={cancelMutation.isPending}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {cancelMutation.isPending ? t('common:loading') : t('upcoming.confirmCancel')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
