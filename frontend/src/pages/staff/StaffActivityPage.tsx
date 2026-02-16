import { useState, useMemo, useCallback, useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '@/hooks/useAuth'
import { staffApi, staffKeys, type StaffEvent } from '@/api/staff'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { useToast } from '@/hooks/use-toast'
import { Calendar, Clock, TrendingUp, Users, CheckCircle2, AlertCircle, FileDown, Search, Loader2 } from 'lucide-react'
import { format, parseISO, addWeeks, isFuture, isPast, startOfMonth, differenceInMinutes } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'

export default function StaffActivityPage() {
  const { t, i18n } = useTranslation(['staff', 'common'])
  const { user } = useAuth()
  const { toast } = useToast()
  const [activeTab, setActiveTab] = useState('upcoming')
  const [exporting, setExporting] = useState(false)

  const locale = i18n.language === 'hu' ? hu : enUS

  // Date filter state
  const now = new Date()
  const defaultDateFrom = format(startOfMonth(now), 'yyyy-MM-dd')
  const defaultDateTo = format(addWeeks(now, 2), 'yyyy-MM-dd')

  const [dateFrom, setDateFrom] = useState(defaultDateFrom)
  const [dateTo, setDateTo] = useState(defaultDateTo)

  // Client search with debounce
  const [clientSearchInput, setClientSearchInput] = useState('')
  const [clientSearch, setClientSearch] = useState('')
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current)
    }
    debounceRef.current = setTimeout(() => {
      setClientSearch(clientSearchInput)
    }, 300)
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current)
      }
    }
  }, [clientSearchInput])

  // Single query for the full date range
  const { data: allEvents, isLoading } = useQuery({
    queryKey: staffKeys.myEvents(dateFrom, dateTo, clientSearch || undefined),
    queryFn: () => staffApi.getMyEvents(dateFrom, dateTo, clientSearch || undefined),
    placeholderData: (prev) => prev,
  })

  // Calculate stats from all events
  const stats = useMemo(() => {
    if (!allEvents || allEvents.length === 0) {
      return {
        totalEvents: 0,
        totalHours: 0,
        totalClients: 0,
        attendanceRate: 0,
      }
    }

    const totalEvents = allEvents.length

    const totalHours = allEvents.reduce((acc, event) => {
      try {
        const start = parseISO(event.starts_at)
        const end = parseISO(event.ends_at)
        return acc + differenceInMinutes(end, start) / 60
      } catch {
        return acc
      }
    }, 0)

    const clientIds = new Set<number>()
    allEvents.forEach(event => {
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

    const pastEvents = allEvents.filter(event => isPast(parseISO(event.starts_at)))
    const attendedEvents = pastEvents.filter(event =>
      event.status === 'completed' || event.status === 'attended'
    ).length
    const attendanceRate = pastEvents.length > 0 ? (attendedEvents / pastEvents.length) * 100 : 0

    return {
      totalEvents,
      totalHours,
      totalClients,
      attendanceRate,
    }
  }, [allEvents])

  // Filter future events for upcoming tab
  const futureEvents = useMemo(() => {
    if (!allEvents) return []
    return allEvents
      .filter(event => isFuture(parseISO(event.starts_at)) || parseISO(event.starts_at) > new Date())
      .sort((a, b) => parseISO(a.starts_at).getTime() - parseISO(b.starts_at).getTime())
  }, [allEvents])

  // Filter past events for history tab
  const historyEvents = useMemo(() => {
    if (!allEvents) return []
    return allEvents
      .filter(event => isPast(parseISO(event.starts_at)))
      .sort((a, b) => parseISO(b.starts_at).getTime() - parseISO(a.starts_at).getTime())
  }, [allEvents])

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

  const handleExport = useCallback(async () => {
    setExporting(true)
    try {
      const blob = await staffApi.downloadActivityXlsx(
        dateFrom,
        dateTo,
        clientSearch || undefined,
        activeTab === 'upcoming' || activeTab === 'history' ? activeTab : undefined
      )

      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `aktivitas_${dateFrom}_${dateTo}.xlsx`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)

      toast({
        title: t('activity.filter.exportSuccess'),
      })
    } catch {
      toast({
        title: t('activity.filter.exportFailed'),
        variant: 'destructive',
      })
    } finally {
      setExporting(false)
    }
  }, [dateFrom, dateTo, clientSearch, activeTab, toast, t])

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('activity.title')}</h1>
        <p className="text-gray-500 mt-2">
          {t('activity.subtitle')} - {user?.name}
        </p>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-col gap-1">
          <label className="text-sm font-medium text-gray-700">{t('activity.filter.dateFrom')}</label>
          <Input
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            className="w-40"
          />
        </div>
        <div className="flex flex-col gap-1">
          <label className="text-sm font-medium text-gray-700">{t('activity.filter.dateTo')}</label>
          <Input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            className="w-40"
          />
        </div>
        <div className="flex flex-col gap-1">
          <label className="text-sm font-medium text-gray-700">{t('activity.filter.clientSearch')}</label>
          <div className="relative">
            <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <Input
              type="text"
              placeholder={t('activity.filter.clientSearch')}
              value={clientSearchInput}
              onChange={(e) => setClientSearchInput(e.target.value)}
              className="w-56 pl-8"
            />
          </div>
        </div>
        <Button
          variant="outline"
          onClick={handleExport}
          disabled={exporting}
        >
          {exporting ? (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          ) : (
            <FileDown className="h-4 w-4 mr-2" />
          )}
          {exporting ? t('activity.filter.exporting') : t('activity.filter.exportExcel')}
        </Button>
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
              {isLoading ? (
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
              {isLoading ? (
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
