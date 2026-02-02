import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '@/hooks/useAuth'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Calendar, Users, Clock, CalendarDays, UserCheck, CheckCircle2, TrendingUp, CreditCard } from 'lucide-react'
import { dashboardApi } from '@/api/dashboard'
import { clientsApi, clientKeys } from '@/api/clients'
import { Skeleton } from '@/components/ui/skeleton'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Link } from 'react-router-dom'
import { format, parseISO } from 'date-fns'
import { hu, enUS } from 'date-fns/locale'

export default function DashboardPage() {
  const { t, i18n } = useTranslation()
  const { user } = useAuth()

  const locale = i18n.language === 'hu' ? hu : enUS

  // Client-specific data
  const clientId = user?.client?.id ? String(user.client.id) : ''
  const isClient = user?.role === 'client'

  // Fetch staff/admin stats (only for non-clients)
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: dashboardApi.getStats,
    enabled: !isClient,
  })

  // Fetch client upcoming bookings (only for clients)
  const { data: upcoming, isLoading: upcomingLoading } = useQuery({
    queryKey: clientKeys.upcoming(clientId),
    queryFn: () => clientsApi.getUpcoming(clientId),
    enabled: isClient && !!clientId,
  })

  // Fetch client activity (only for clients)
  const { data: activity, isLoading: activityLoading } = useQuery({
    queryKey: clientKeys.activity(clientId),
    queryFn: () => clientsApi.getActivity(clientId),
    enabled: isClient && !!clientId,
  })

  // Fetch client passes (only for clients)
  const { data: passes, isLoading: passesLoading } = useQuery({
    queryKey: clientKeys.passes(clientId),
    queryFn: () => clientsApi.getPasses(clientId),
    enabled: isClient && !!clientId,
  })

  const formatDateTime = (dateStr: string) => {
    try {
      return format(parseISO(dateStr), 'MMM d. HH:mm', { locale })
    } catch {
      return dateStr
    }
  }

  // Staff/Admin dashboard
  if (!isClient) {
    const statCards = [
      {
        name: t('dashboard.todayEvents', 'Mai események'),
        value: stats?.today_events ?? 0,
        description: t('dashboard.todayEventsDesc', 'Edzések és órák ma'),
        icon: Calendar,
        color: 'text-blue-600',
        bgColor: 'bg-blue-100',
      },
      {
        name: t('dashboard.weeklyHours', 'Heti órák'),
        value: `${stats?.weekly_hours ?? 0}h`,
        description: t('dashboard.weeklyHoursDesc', 'Ledolgozott órák ezen a héten'),
        icon: Clock,
        color: 'text-purple-600',
        bgColor: 'bg-purple-100',
      },
      {
        name: t('dashboard.activeClients', 'Aktív vendégek'),
        value: stats?.active_clients ?? 0,
        description: t('dashboard.activeClientsDesc', 'Regisztrált vendégek száma'),
        icon: Users,
        color: 'text-green-600',
        bgColor: 'bg-green-100',
      },
      {
        name: t('dashboard.upcomingEvents', 'Közelgő események'),
        value: stats?.upcoming_events ?? 0,
        description: t('dashboard.upcomingEventsDesc', 'Események a következő 7 napban'),
        icon: CalendarDays,
        color: 'text-orange-600',
        bgColor: 'bg-orange-100',
      },
      {
        name: t('dashboard.todayBookings', 'Mai foglalások'),
        value: stats?.today_bookings ?? 0,
        description: t('dashboard.todayBookingsDesc', 'Csoportos órákra jelentkezettek'),
        icon: UserCheck,
        color: 'text-teal-600',
        bgColor: 'bg-teal-100',
      },
    ]

    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">
            {t('navigation.dashboard')}
          </h1>
          <p className="text-gray-500 mt-2">
            {t('dashboard.welcome', 'Üdvözlünk')}, {user?.name}!
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          {statCards.map((stat) => {
            const Icon = stat.icon
            return (
              <Card key={stat.name}>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">
                    {stat.name}
                  </CardTitle>
                  <div className={`${stat.bgColor} p-2 rounded-lg`}>
                    <Icon className={`h-4 w-4 ${stat.color}`} />
                  </div>
                </CardHeader>
                <CardContent>
                  {statsLoading ? (
                    <Skeleton className="h-8 w-16" />
                  ) : (
                    <div className="text-2xl font-bold">{stat.value}</div>
                  )}
                  <p className="text-xs text-gray-500 mt-1">
                    {stat.description}
                  </p>
                </CardContent>
              </Card>
            )
          })}
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('dashboard.recentActivity', 'Legutóbbi tevékenység')}</CardTitle>
            <CardDescription>
              {t('dashboard.recentActivityDesc', 'Legutóbbi foglalások és események')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-gray-500">
              {t('dashboard.noRecentActivity', 'Nincs megjeleníthető tevékenység.')}
            </p>
          </CardContent>
        </Card>
      </div>
    )
  }

  // Client dashboard
  const clientLoading = upcomingLoading || activityLoading || passesLoading

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">
          {t('dashboard.welcome', 'Üdvözlünk')}, {user?.name}!
        </h1>
        <p className="text-gray-500 mt-2">
          {t('dashboard.clientSubtitle', 'Itt láthatod a foglalásaidat és bérleteidet')}
        </p>
      </div>

      {/* Client Stats */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('dashboard.upcomingBookings', 'Közelgő foglalások')}
            </CardTitle>
            <Calendar className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            {clientLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{upcoming?.length || 0}</div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('dashboard.attendedSessions', 'Részvételek')}
            </CardTitle>
            <CheckCircle2 className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            {clientLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{activity?.summary?.attended_sessions || 0}</div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('dashboard.attendanceRate', 'Részvételi arány')}
            </CardTitle>
            <TrendingUp className="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            {clientLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">
                {activity?.summary?.attendance_rate?.toFixed(0) ?? '0'}%
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">
              {t('dashboard.creditsRemaining', 'Hátralévő alkalmak')}
            </CardTitle>
            <CreditCard className="h-4 w-4 text-orange-600" />
          </CardHeader>
          <CardContent>
            {clientLoading ? (
              <Skeleton className="h-8 w-16" />
            ) : (
              <div className="text-2xl font-bold">{passes?.total_credits_remaining || 0}</div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Upcoming Bookings */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>{t('dashboard.upcomingBookings', 'Közelgő foglalások')}</CardTitle>
            <CardDescription>
              {t('dashboard.nextBookingsDesc', 'A legközelebbi edzéseid')}
            </CardDescription>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link to="/activity">{t('dashboard.viewAll', 'Összes megtekintése')}</Link>
          </Button>
        </CardHeader>
        <CardContent>
          {upcomingLoading ? (
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-16 w-full" />
              ))}
            </div>
          ) : upcoming && upcoming.length > 0 ? (
            <div className="space-y-3">
              {upcoming.slice(0, 5).map((booking) => (
                <div
                  key={booking.id}
                  className="flex items-center justify-between p-3 border rounded-lg"
                >
                  <div>
                    <div className="flex items-center gap-2">
                      <h4 className="font-medium">{booking.title}</h4>
                      <Badge variant="outline">
                        {booking.type === 'class' ? t('client:activity.item.class', 'Óra') : t('client:activity.item.event', 'Esemény')}
                      </Badge>
                    </div>
                    <div className="flex items-center gap-2 mt-1 text-sm text-gray-500">
                      <Clock className="h-3 w-3" />
                      {formatDateTime(booking.starts_at)}
                      {booking.room && <span>• {booking.room}</span>}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8">
              <Calendar className="h-12 w-12 mx-auto text-gray-300 mb-4" />
              <p className="text-sm text-gray-500 mb-4">
                {t('dashboard.noUpcomingBookings', 'Nincs közelgő foglalásod')}
              </p>
              <Button asChild>
                <Link to="/classes">{t('dashboard.browseClasses', 'Órák böngészése')}</Link>
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Active Passes */}
      {passes && passes.active_passes.length > 0 && (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle>{t('dashboard.activePasses', 'Aktív bérletek')}</CardTitle>
              <CardDescription>
                {t('dashboard.passesDesc', 'Érvényes bérleteid és egyenleged')}
              </CardDescription>
            </div>
            <Button variant="outline" size="sm" asChild>
              <Link to="/activity">{t('dashboard.viewAll', 'Összes megtekintése')}</Link>
            </Button>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2">
              {passes.active_passes.slice(0, 2).map((pass) => (
                <div key={pass.id} className="p-4 border rounded-lg bg-blue-50 border-blue-200">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium">{pass.pass_type}</h4>
                    <Badge variant="default">{t('dashboard.active', 'Aktív')}</Badge>
                  </div>
                  <p className="text-2xl font-bold text-blue-600">
                    {pass.credits_remaining === null
                      ? t('dashboard.unlimited', 'Korlátlan')
                      : `${pass.credits_remaining} / ${pass.credits_total}`}
                  </p>
                  <p className="text-xs text-gray-500 mt-1">
                    {t('dashboard.creditsRemaining', 'Hátralévő alkalmak')}
                  </p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
