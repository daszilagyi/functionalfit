import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/hooks/useAuth'
import { clientReportsApi, reportKeys } from '@/api/reports'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Calendar as CalendarIcon, TrendingUp, Award, CreditCard } from 'lucide-react'
import { format, subDays, subMonths } from 'date-fns'

type PeriodType = 'last_30_days' | 'last_3_months' | 'custom'

export default function ClientReportsPage() {
  const { t } = useTranslation(['client', 'common'])
  const { user } = useAuth()

  const clientId = user?.client?.id

  // Period selection state
  const [periodType, setPeriodType] = useState<PeriodType>('last_30_days')
  const [customDateFrom, setCustomDateFrom] = useState(
    format(subDays(new Date(), 30), 'yyyy-MM-dd')
  )
  const [customDateTo, setCustomDateTo] = useState(format(new Date(), 'yyyy-MM-dd'))

  // Calculate actual date range based on period type
  const getDateRange = () => {
    if (periodType === 'last_30_days') {
      return {
        from: format(subDays(new Date(), 30), 'yyyy-MM-dd'),
        to: format(new Date(), 'yyyy-MM-dd'),
      }
    } else if (periodType === 'last_3_months') {
      return {
        from: format(subMonths(new Date(), 3), 'yyyy-MM-dd'),
        to: format(new Date(), 'yyyy-MM-dd'),
      }
    } else {
      return {
        from: customDateFrom,
        to: customDateTo,
      }
    }
  }

  const { from: dateFrom, to: dateTo } = getDateRange()

  // Fetch summary stats
  const { data: summaryData, isLoading: isLoadingSummary } = useQuery({
    queryKey: reportKeys.client.summary(clientId!, dateFrom, dateTo),
    queryFn: () => clientReportsApi.getSummaryStats(clientId!, dateFrom, dateTo),
    enabled: !!clientId,
  })

  // Fetch session history
  const { data: sessionHistory, isLoading: isLoadingSessions } = useQuery({
    queryKey: reportKeys.client.sessions(clientId!, dateFrom, dateTo),
    queryFn: () => clientReportsApi.getSessionHistory(clientId!, dateFrom, dateTo),
    enabled: !!clientId,
  })

  // Fetch passes
  const { data: passes, isLoading: isLoadingPasses } = useQuery({
    queryKey: reportKeys.client.passes(clientId!),
    queryFn: () => clientReportsApi.getPasses(clientId!),
    enabled: !!clientId,
  })

  if (!clientId) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <p className="text-gray-500">{t('common:notAuthorized')}</p>
      </div>
    )
  }

  return (
    <div className="space-y-6" data-testid="client-reports-page">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('reports.title')}</h1>
        <p className="text-gray-500 mt-2">{t('reports.subtitle')}</p>
      </div>

      {/* Period Selector */}
      <Card>
        <CardHeader>
          <CardTitle>{t('reports.period.title')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="period-select">{t('reports.period.title')}</Label>
              <Select
                value={periodType}
                onValueChange={(value) => setPeriodType(value as PeriodType)}
              >
                <SelectTrigger id="period-select" data-testid="period-selector">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="last_30_days">{t('reports.period.last30Days')}</SelectItem>
                  <SelectItem value="last_3_months">{t('reports.period.last3Months')}</SelectItem>
                  <SelectItem value="custom">{t('reports.period.customPeriod')}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {periodType === 'custom' && (
              <div className="flex items-end gap-4">
                <div className="space-y-2 flex-1">
                  <Label htmlFor="custom_date_from">{t('reports.period.dateFrom')}</Label>
                  <Input
                    id="custom_date_from"
                    type="date"
                    value={customDateFrom}
                    onChange={(e) => setCustomDateFrom(e.target.value)}
                    data-testid="custom-date-from"
                  />
                </div>
                <div className="space-y-2 flex-1">
                  <Label htmlFor="custom_date_to">{t('reports.period.dateTo')}</Label>
                  <Input
                    id="custom_date_to"
                    type="date"
                    value={customDateTo}
                    onChange={(e) => setCustomDateTo(e.target.value)}
                    data-testid="custom-date-to"
                  />
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Summary Stats */}
      {isLoadingSummary ? (
        <div className="grid gap-4 md:grid-cols-4">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      ) : summaryData ? (
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.summary.totalSessions')}</CardTitle>
              <CalendarIcon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{summaryData.total_sessions}</div>
              <p className="text-xs text-muted-foreground">{t('activity.totalSessions')}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.summary.attendedSessions')}</CardTitle>
              <Award className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">{summaryData.attended_sessions}</div>
              <p className="text-xs text-muted-foreground">{t('activity.attendedSessions')}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.summary.attendanceRate')}</CardTitle>
              <TrendingUp className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{summaryData.attendance_rate.toFixed(1)}%</div>
              <p className="text-xs text-muted-foreground">{t('activity.attendanceRate')}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.summary.totalCreditsUsed')}</CardTitle>
              <CreditCard className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{summaryData.total_credits_used}</div>
              <p className="text-xs text-muted-foreground">{t('activity.totalCreditsUsed')}</p>
            </CardContent>
          </Card>
        </div>
      ) : null}

      {/* Session History Table */}
      <Card>
        <CardHeader>
          <CardTitle>{t('reports.sessionHistory.title')}</CardTitle>
          <CardDescription>
            {dateFrom} - {dateTo}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoadingSessions ? (
            <div className="space-y-2">
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
            </div>
          ) : sessionHistory && sessionHistory.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('reports.sessionHistory.date')}</TableHead>
                  <TableHead>{t('reports.sessionHistory.service')}</TableHead>
                  <TableHead>{t('reports.sessionHistory.trainer')}</TableHead>
                  <TableHead>{t('reports.sessionHistory.room')}</TableHead>
                  <TableHead className="text-right">{t('reports.sessionHistory.credits')}</TableHead>
                  <TableHead>{t('reports.sessionHistory.status')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {sessionHistory.map((session) => (
                  <TableRow key={session.id}>
                    <TableCell className="font-medium">
                      {session.date} {session.time}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col">
                        <span className="font-medium">{session.service_name}</span>
                        <Badge
                          variant={session.type === 'INDIVIDUAL' ? 'default' : 'secondary'}
                          className="w-fit mt-1"
                        >
                          {session.type === 'INDIVIDUAL' ? '1:1' : t('common:group')}
                        </Badge>
                      </div>
                    </TableCell>
                    <TableCell>{session.trainer_name}</TableCell>
                    <TableCell>{session.room_name}</TableCell>
                    <TableCell className="text-right">{session.credits_used}</TableCell>
                    <TableCell>
                      {session.attendance_status === 'attended' && (
                        <Badge variant="default" className="bg-green-600">
                          {t('activity.item.attended')}
                        </Badge>
                      )}
                      {session.attendance_status === 'no_show' && (
                        <Badge variant="destructive">{t('activity.item.noShow')}</Badge>
                      )}
                      {!session.attendance_status && (
                        <Badge variant="outline">{t('upcoming.title')}</Badge>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <p className="text-center text-gray-500 py-8">{t('reports.sessionHistory.noHistory')}</p>
          )}
        </CardContent>
      </Card>

      {/* Active Passes Summary */}
      <Card>
        <CardHeader>
          <CardTitle>{t('reports.payments.title')}</CardTitle>
          <CardDescription>{t('passes.activePasses')}</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoadingPasses ? (
            <div className="space-y-2">
              <Skeleton className="h-20 w-full" />
              <Skeleton className="h-20 w-full" />
            </div>
          ) : passes && passes.length > 0 ? (
            <div className="space-y-4">
              {/* Summary Stats */}
              <div className="grid gap-4 md:grid-cols-3">
                <div className="p-4 border rounded-lg">
                  <p className="text-sm font-medium text-muted-foreground">{t('reports.payments.totalCredits')}</p>
                  <p className="text-2xl font-bold">
                    {passes.reduce((sum, pass) => sum + pass.total_credits, 0)}
                  </p>
                </div>
                <div className="p-4 border rounded-lg">
                  <p className="text-sm font-medium text-muted-foreground">{t('reports.payments.usedCredits')}</p>
                  <p className="text-2xl font-bold text-orange-600">
                    {passes.reduce((sum, pass) => sum + (pass.total_credits - pass.remaining_credits), 0)}
                  </p>
                </div>
                <div className="p-4 border rounded-lg">
                  <p className="text-sm font-medium text-muted-foreground">{t('reports.payments.remainingCredits')}</p>
                  <p className="text-2xl font-bold text-green-600">
                    {passes.reduce((sum, pass) => sum + pass.remaining_credits, 0)}
                  </p>
                </div>
              </div>

              {/* Passes List */}
              <div className="space-y-2">
                <h4 className="text-sm font-semibold">{t('passes.activePasses')}</h4>
                {passes
                  .filter((pass) => pass.status === 'active')
                  .map((pass) => (
                    <div key={pass.id} className="flex items-center justify-between p-3 border rounded-lg">
                      <div>
                        <p className="font-medium">{pass.name}</p>
                        <p className="text-sm text-muted-foreground">
                          {t('passes.passDetails.remaining')}: {pass.remaining_credits} / {pass.total_credits}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-sm font-medium">
                          {pass.price.toLocaleString()} {pass.currency}
                        </p>
                        <Badge variant="default" className="mt-1">
                          {t('passes.status.active')}
                        </Badge>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          ) : (
            <p className="text-center text-gray-500 py-8">{t('passes.noActivePasses')}</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
