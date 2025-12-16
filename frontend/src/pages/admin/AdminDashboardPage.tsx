import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Users, DoorOpen, Calendar, TrendingUp, UserPlus, PlusCircle, Dumbbell, Wallet } from 'lucide-react'
import { usersApi, roomsApi, reportsApi, adminKeys } from '@/api/admin'
import { format } from 'date-fns'

/**
 * Formats a number as Hungarian Forint currency
 * @param amount - The amount to format
 * @returns Formatted string like "1 000 Ft"
 */
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('hu-HU', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount) + ' Ft'
}

export default function AdminDashboardPage() {
  const { t } = useTranslation('admin')

  // Calculate date range for last 30 days
  const dateTo = format(new Date(), 'yyyy-MM-dd')
  const dateFrom = format(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000), 'yyyy-MM-dd')

  // Fetch overview stats
  const { data: usersData, isLoading: usersLoading } = useQuery({
    queryKey: adminKeys.usersList(),
    queryFn: () => usersApi.list(),
  })

  const { data: roomsData, isLoading: roomsLoading } = useQuery({
    queryKey: adminKeys.roomsList(),
    queryFn: () => roomsApi.list(),
  })

  const { data: attendanceData, isLoading: attendanceLoading } = useQuery({
    queryKey: adminKeys.attendanceReport(dateFrom, dateTo),
    queryFn: () => reportsApi.attendance(dateFrom, dateTo),
  })

  const { data: utilizationData, isLoading: utilizationLoading } = useQuery({
    queryKey: adminKeys.utilizationReport(dateFrom, dateTo),
    queryFn: () => reportsApi.utilization(dateFrom, dateTo),
  })

  const isLoading = usersLoading || roomsLoading || attendanceLoading || utilizationLoading

  // Calculate total unpaid balance from all clients
  const totalUnpaidBalance = useMemo(() => {
    if (!usersData?.data) return 0
    return usersData.data.reduce((sum, user) => {
      if (user.role === 'client' && user.client?.unpaid_balance) {
        // API returns string, convert to number
        const balance = parseFloat(String(user.client.unpaid_balance)) || 0
        return sum + balance
      }
      return sum
    }, 0)
  }, [usersData])

  // Count clients with unpaid balance
  const clientsWithUnpaidBalance = useMemo(() => {
    if (!usersData?.data) return 0
    return usersData.data.filter(
      (user) => user.role === 'client' && (user.client?.unpaid_balance ?? 0) > 0
    ).length
  }, [usersData])

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('dashboard.title')}</h1>
          <p className="text-gray-500 mt-2">{t('dashboard.subtitle')}</p>
        </div>

        {/* Loading skeleton for KPI cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
          {[1, 2, 3, 4, 5].map((i) => (
            <Card key={i}>
              <CardHeader>
                <Skeleton className="h-4 w-24" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-8 w-16" />
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Loading skeleton for charts */}
        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <Skeleton className="h-5 w-32" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-64 w-full" />
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <Skeleton className="h-5 w-32" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-64 w-full" />
            </CardContent>
          </Card>
        </div>
      </div>
    )
  }

  const quickActions = [
    {
      title: t('users.createUser'),
      icon: UserPlus,
      href: '/admin/users',
      color: 'bg-blue-500 hover:bg-blue-600',
    },
    {
      title: t('rooms.createRoom'),
      icon: PlusCircle,
      href: '/admin/rooms',
      color: 'bg-green-500 hover:bg-green-600',
    },
    {
      title: t('classTemplates.createTemplate'),
      icon: Dumbbell,
      href: '/admin/class-templates',
      color: 'bg-purple-500 hover:bg-purple-600',
    },
  ]

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('dashboard.title')}</h1>
        <p className="text-gray-500 mt-2">{t('dashboard.subtitle')}</p>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
        {/* Total Users */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t('dashboard.totalUsers')}</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{usersData?.data?.length ?? 0}</div>
            <p className="text-xs text-muted-foreground">{t('dashboard.active')}</p>
          </CardContent>
        </Card>

        {/* Total Rooms */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t('dashboard.totalRooms')}</CardTitle>
            <DoorOpen className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{roomsData?.length ?? 0}</div>
            <p className="text-xs text-muted-foreground">{t('dashboard.active')}</p>
          </CardContent>
        </Card>

        {/* Total Sessions (30 days) */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t('dashboard.totalSessions30d')}</CardTitle>
            <Calendar className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{utilizationData?.summary.total_sessions ?? 0}</div>
            <p className="text-xs text-muted-foreground">{t('dashboard.last30Days')}</p>
          </CardContent>
        </Card>

        {/* Attendance Rate */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t('dashboard.attendanceRate')}</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {attendanceData?.summary.attendance_rate ? Math.round(attendanceData.summary.attendance_rate) : 0}%
            </div>
            <p className="text-xs text-muted-foreground">
              {attendanceData?.summary.total_no_shows ?? 0} {t('dashboard.noShow')}
            </p>
          </CardContent>
        </Card>

        {/* Total Unpaid Balance */}
        <Card data-testid="unpaid-balance-card">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{t('dashboard.totalUnpaidBalance')}</CardTitle>
            <Wallet className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className={`text-2xl font-bold ${totalUnpaidBalance > 0 ? 'text-destructive' : ''}`}>
              {formatCurrency(totalUnpaidBalance)}
            </div>
            <p className="text-xs text-muted-foreground">
              {clientsWithUnpaidBalance} {t('dashboard.clientsWithDebt')}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <Card>
        <CardHeader>
          <CardTitle>{t('dashboard.quickActions')}</CardTitle>
          <CardDescription>{t('dashboard.quickActionsDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {quickActions.map((action) => {
              const Icon = action.icon
              return (
                <Link key={action.title} to={action.href}>
                  <Button
                    className={`w-full h-24 ${action.color} text-white`}
                    variant="default"
                  >
                    <div className="flex flex-col items-center gap-2">
                      <Icon className="h-6 w-6" />
                      <span>{action.title}</span>
                    </div>
                  </Button>
                </Link>
              )
            })}
          </div>
        </CardContent>
      </Card>

      {/* Charts Row */}
      <div className="grid gap-4 md:grid-cols-2">
        {/* Top Staff */}
        <Card>
          <CardHeader>
            <CardTitle>{t('dashboard.topStaff')}</CardTitle>
            <CardDescription>{t('dashboard.topStaffDescription')}</CardDescription>
          </CardHeader>
          <CardContent>
            {utilizationData && utilizationData.staff_utilization.length > 0 ? (
              <div className="space-y-4">
                {utilizationData.staff_utilization
                  .sort((a, b) => b.total_sessions - a.total_sessions)
                  .slice(0, 5)
                  .map((staff, index) => (
                    <div key={staff.staff_id} className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold">
                          {index + 1}
                        </div>
                        <div>
                          <p className="text-sm font-medium">{staff.name}</p>
                          <p className="text-xs text-muted-foreground">
                            {staff.individual_sessions} {t('dashboard.individual')} + {staff.group_classes}{' '}
                            {t('dashboard.group')}
                          </p>
                        </div>
                      </div>
                      <div className="text-sm font-semibold">
                        {staff.total_sessions} {t('dashboard.sessions')}
                      </div>
                    </div>
                  ))}
              </div>
            ) : (
              <p className="text-sm text-gray-500">{t('common:noData')}</p>
            )}
          </CardContent>
        </Card>

        {/* Attendance Breakdown */}
        <Card>
          <CardHeader>
            <CardTitle>{t('dashboard.attendanceBreakdown')}</CardTitle>
            <CardDescription>{t('dashboard.last30Days')}</CardDescription>
          </CardHeader>
          <CardContent>
            {attendanceData ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="h-3 w-3 rounded-full bg-green-500" />
                    <span className="text-sm">{t('dashboard.attended')}</span>
                  </div>
                  <Badge variant="default">{attendanceData.summary.total_attended}</Badge>
                </div>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="h-3 w-3 rounded-full bg-red-500" />
                    <span className="text-sm">{t('dashboard.noShows')}</span>
                  </div>
                  <Badge variant="destructive">{attendanceData.summary.total_no_shows}</Badge>
                </div>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="h-3 w-3 rounded-full bg-gray-300" />
                    <span className="text-sm">{t('dashboard.notCheckedIn')}</span>
                  </div>
                  <Badge variant="secondary">{attendanceData.summary.not_checked_in}</Badge>
                </div>
                <div className="border-t pt-2 mt-2">
                  <div className="flex items-center justify-between font-semibold">
                    <span className="text-sm">{t('dashboard.total')}</span>
                    <Badge>{attendanceData.summary.total_sessions}</Badge>
                  </div>
                </div>
              </div>
            ) : (
              <p className="text-sm text-gray-500">{t('common:noData')}</p>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Session Type Breakdown */}
      <Card>
        <CardHeader>
          <CardTitle>{t('dashboard.sessionTypeBreakdown')}</CardTitle>
          <CardDescription>{t('dashboard.last30Days')}</CardDescription>
        </CardHeader>
        <CardContent>
          {attendanceData ? (
            <div className="grid gap-4 md:grid-cols-2">
              <div className="flex items-center justify-between p-4 border rounded-lg">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('dashboard.individualSessions')}</p>
                  <p className="text-2xl font-bold">{attendanceData.by_type.individual.total}</p>
                </div>
                <div className="text-4xl">💪</div>
              </div>
              <div className="flex items-center justify-between p-4 border rounded-lg">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('dashboard.groupClasses')}</p>
                  <p className="text-2xl font-bold">{attendanceData.by_type.group_classes.total}</p>
                </div>
                <div className="text-4xl">👥</div>
              </div>
            </div>
          ) : (
            <p className="text-sm text-gray-500">{t('common:noData')}</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
