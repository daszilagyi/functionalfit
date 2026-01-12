import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '@/hooks/useAuth'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Calendar, Users, Clock, CalendarDays, UserCheck } from 'lucide-react'
import { dashboardApi } from '@/api/dashboard'
import { Skeleton } from '@/components/ui/skeleton'

export default function DashboardPage() {
  const { t } = useTranslation()
  const { user } = useAuth()

  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: dashboardApi.getStats,
  })

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
                {isLoading ? (
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
