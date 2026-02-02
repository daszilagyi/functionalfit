import { useState, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '@/hooks/useAuth'
import { staffApi, staffKeys, type StaffEvent } from '@/api/staff'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Calendar, Clock, TrendingUp, Users, CheckCircle2, AlertCircle } from 'lucide-react'
import { format, parseISO, startOfWeek, endOfWeek, addWeeks, isFuture, isPast, startOfMonth, endOfMonth, differenceInMinutes } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'

export default function StaffActivityPage() {
  const { t, i18n } = useTranslation(['staff', 'common'])
  const { user } = useAuth()
  const [activeTab, setActiveTab] = useState('upcoming')

  const locale = i18n.language === 'hu' ? hu : enUS

  // Calculate date ranges
  const now = new Date()
  const weekStart = startOfWeek(now, { weekStartsOn: 1 })
  const nextWeekEnd = endOfWeek(addWeeks(now, 1), { weekStartsOn: 1 })
  const monthStart = startOfMonth(now)
  const monthEnd = endOfMonth(now)

  // Fetch all events for the month (for stats calculation)
  const { data: monthEvents, isLoading: monthLoading } = useQuery({
    queryKey: staffKeys.myEvents(format(monthStart, 'yyyy-MM-dd'), format(monthEnd, 'yyyy-MM-dd')),
    queryFn: () => staffApi.getMyEvents(format(monthStart, 'yyyy-MM-dd'), format(monthEnd, 'yyyy-MM-dd')),
  })

  // Fetch upcoming events (current week + next week)
  const { data: upcomingEvents, isLoading: upcomingLoading } = useQuery({
    queryKey: staffKeys.myEvents(format(weekStart, 'yyyy-MM-dd'), format(nextWeekEnd, 'yyyy-MM-dd')),
    queryFn: () => staffApi.getMyEvents(format(weekStart, 'yyyy-MM-dd'), format(nextWeekEnd, 'yyyy-MM-dd')),
  })

  // Calculate stats from month events
  const stats = useMemo(() => {
    if (!monthEvents || monthEvents.length === 0) {
      return {
        totalEvents: 0,
        totalHours: 0,
        totalClients: 0,
        attendanceRate: 0,
      }
    }

    // Total events
    const totalEvents = monthEvents.length

    // Total hours
    const totalHours = monthEvents.reduce((acc, event) => {
      try {
        const start = parseISO(event.starts_at)
        const end = parseISO(event.ends_at)
        return acc + differenceInMinutes(end, start) / 60
      } catch {
        return acc
      }
    }, 0)

    // Unique clients
    const clientIds = new Set<number>()
    monthEvents.forEach(event => {
      if (event.client?.id) {
        clientIds.add(event.client.id)
      }
      event.additional_clients?.forEach(client => {
        if (client.id) {
          clientIds.add(client.id)
        }
      })
    })
    const totalClients = clientIds.size

    // Attendance rate (from past events only)
    const pastMonthEvents = monthEvents.filter(event => isPast(parseISO(event.starts_at)))
    const attendedEvents = pastMonthEvents.filter(event =>
      event.status === 'completed' || event.status === 'attended'
    ).length
    const totalPastEvents = pastMonthEvents.length
    const attendanceRate = totalPastEvents > 0 ? (attendedEvents / totalPastEvents) * 100 : 0

    return {
      totalEvents,
      totalHours,
      totalClients,
      attendanceRate,
    }
  }, [monthEvents])

  // Filter future events
  const futureEvents = useMemo(() => {
    if (!upcomingEvents) return []
    return upcomingEvents
      .filter(event => isFuture(parseISO(event.starts_at)) || parseISO(event.starts_at) > new Date())
      .sort((a, b) => parseISO(a.starts_at).getTime() - parseISO(b.starts_at).getTime())
  }, [upcomingEvents])

  // Filter past events for history (from month events)
  const historyEvents = useMemo(() => {
    if (!monthEvents) return []
    return monthEvents
      .filter(event => isPast(parseISO(event.starts_at)))
      .sort((a, b) => parseISO(b.starts_at).getTime() - parseISO(a.starts_at).getTime())
  }, [monthEvents])

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

  const formatTime = (dateStr: string) => {
    try {
      return format(parseISO(dateStr), 'HH:mm', { locale })
    } catch {
      return dateStr
    }
  }

  const getEventTitle = (event: StaffEvent) => {
    if (event.title) return event.title
    if (event.client?.user?.name) return `${event.type === 'INDIVIDUAL' ? '1:1' : ''} ${event.client.user.name}`
    return event.type === 'INDIVIDUAL' ? t('activity.individualSession') : t('activity.groupSession')
  }

  const getStatusBadge = (status: string) => {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline', label: string }> = {
      scheduled: { variant: 'default', label: t('activity.status.scheduled') },
      completed: { variant: 'secondary', label: t('activity.status.completed') },
      cancelled: { variant: 'destructive', label: t('activity.status.cancelled') },
      no_show: { variant: 'destructive', label: t('activity.status.noShow') },
    }
    const config = variants[status] || variants.scheduled
    return <Badge variant={config.variant}>{config.label}</Badge>
  }

  const isLoading = monthLoading || upcomingLoading

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('activity.title')}</h1>
        <p className="text-gray-500 mt-2">
          {t('activity.subtitle')} - {user?.name}
        </p>
      </div>

      {/* Summary Stats */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('activity.stats.totalEvents')}
            </CardTitle>
            <Calendar className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{stats.totalEvents}</div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('activity.stats.totalHours')}
            </CardTitle>
            <Clock className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{stats.totalHours.toFixed(1)}</div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('activity.stats.totalClients')}
            </CardTitle>
            <Users className="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{stats.totalClients}</div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('activity.stats.attendanceRate')}
            </CardTitle>
            <TrendingUp className="h-4 w-4 text-orange-600" />
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">
                {stats.attendanceRate.toFixed(0)}%
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList>
          <TabsTrigger value="upcoming">{t('activity.tabs.upcoming')}</TabsTrigger>
          <TabsTrigger value="history">{t('activity.tabs.history')}</TabsTrigger>
        </TabsList>

        {/* Upcoming Events Tab */}
        <TabsContent value="upcoming" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{t('activity.upcoming.title')}</CardTitle>
              <CardDescription>
                {futureEvents.length} {t('activity.upcoming.eventsCount')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {upcomingLoading ? (
                <div className="space-y-3">
                  {[1, 2, 3].map((i) => (
                    <Skeleton key={i} className="h-20 w-full" />
                  ))}
                </div>
              ) : futureEvents.length > 0 ? (
                <div className="space-y-3">
                  {futureEvents.map((event) => (
                    <div
                      key={event.id}
                      className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                    >
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <h3 className="font-medium">{getEventTitle(event)}</h3>
                          <Badge variant="outline">
                            {event.type === 'INDIVIDUAL' ? '1:1' : t('activity.groupSession')}
                          </Badge>
                        </div>
                        <div className="flex items-center gap-4 mt-1 text-sm text-gray-500">
                          <div className="flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {formatDateTime(event.starts_at)}
                          </div>
                          {event.room && <span>• {event.room.name}</span>}
                          {event.additional_clients && event.additional_clients.length > 0 && (
                            <span>• +{event.additional_clients.length} {t('activity.additionalClients')}</span>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        {getStatusBadge(event.status)}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <AlertCircle className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                  <p className="text-sm text-gray-500">
                    {t('activity.upcoming.noEvents')}
                  </p>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* History Tab */}
        <TabsContent value="history" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{t('activity.history.title')}</CardTitle>
              <CardDescription>
                {historyEvents.length} {t('activity.history.eventsCount')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {monthLoading ? (
                <div className="space-y-3">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Skeleton key={i} className="h-16 w-full" />
                  ))}
                </div>
              ) : historyEvents.length > 0 ? (
                <div className="space-y-2">
                  {historyEvents.map((event) => (
                    <div
                      key={event.id}
                      className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50"
                    >
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <h4 className="font-medium">{getEventTitle(event)}</h4>
                          <Badge variant="outline">
                            {event.type === 'INDIVIDUAL' ? '1:1' : t('activity.groupSession')}
                          </Badge>
                          {event.status === 'completed' && (
                            <Badge variant="secondary" className="bg-green-100 text-green-700">
                              <CheckCircle2 className="h-3 w-3 mr-1" />
                              {t('activity.status.completed')}
                            </Badge>
                          )}
                          {event.status === 'no_show' && (
                            <Badge variant="destructive">
                              {t('activity.status.noShow')}
                            </Badge>
                          )}
                        </div>
                        <div className="flex items-center gap-4 mt-1 text-sm text-gray-500">
                          <span>{formatDate(event.starts_at)}</span>
                          <span>
                            {formatTime(event.starts_at)} - {formatTime(event.ends_at)}
                          </span>
                          {event.room && <span>• {event.room.name}</span>}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <Calendar className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                  <p className="text-sm text-gray-500">
                    {t('activity.history.noEvents')}
                  </p>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
