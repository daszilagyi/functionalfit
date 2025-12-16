import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { staffReportsApi, reportKeys } from '@/api/reports'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
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
import { useToast } from '@/hooks/use-toast'
import { FileDown, TrendingUp, Clock, DollarSign, Calendar as CalendarIcon } from 'lucide-react'
import { format, subDays } from 'date-fns'
import { Badge } from '@/components/ui/badge'

export default function StaffReportsPage() {
  const { t } = useTranslation(['staff', 'common'])
  const { toast } = useToast()

  // Date range state - default to last 30 days
  const [dateFrom, setDateFrom] = useState(format(subDays(new Date(), 30), 'yyyy-MM-dd'))
  const [dateTo, setDateTo] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [isExporting, setIsExporting] = useState(false)

  // Fetch payout report data (JSON)
  const { data: payoutData, isLoading: isLoadingPayout } = useQuery({
    queryKey: reportKeys.staff.payout(dateFrom, dateTo),
    queryFn: () => staffReportsApi.getPayoutReport(dateFrom, dateTo),
  })

  // Fetch attendance report data (JSON)
  const { data: attendanceData, isLoading: isLoadingAttendance } = useQuery({
    queryKey: reportKeys.staff.attendance(dateFrom, dateTo),
    queryFn: () => staffReportsApi.getAttendanceReport(dateFrom, dateTo),
  })

  const handleExportPayout = async () => {
    try {
      setIsExporting(true)
      toast({
        title: t('export.downloading'),
        description: t('export.payoutReport'),
      })

      const blob = await staffReportsApi.downloadPayoutXlsx(dateFrom, dateTo)

      // Create download link
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `payout_${dateFrom}_${dateTo}.xlsx`
      document.body.appendChild(link)
      link.click()

      // Cleanup
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)

      toast({
        title: t('export.downloadSuccess'),
        description: `payout_${dateFrom}_${dateTo}.xlsx`,
      })
    } catch (error) {
      console.error('Export error:', error)
      toast({
        variant: 'destructive',
        title: t('export.downloadFailed'),
        description: t('common:errorOccurred'),
      })
    } finally {
      setIsExporting(false)
    }
  }

  const handleExportAttendance = async () => {
    try {
      setIsExporting(true)
      toast({
        title: t('export.downloading'),
        description: t('export.attendanceReport'),
      })

      const blob = await staffReportsApi.downloadAttendanceXlsx(dateFrom, dateTo)

      // Create download link
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `attendance_${dateFrom}_${dateTo}.xlsx`
      document.body.appendChild(link)
      link.click()

      // Cleanup
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)

      toast({
        title: t('export.downloadSuccess'),
        description: `attendance_${dateFrom}_${dateTo}.xlsx`,
      })
    } catch (error) {
      console.error('Export error:', error)
      toast({
        variant: 'destructive',
        title: t('export.downloadFailed'),
        description: t('common:errorOccurred'),
      })
    } finally {
      setIsExporting(false)
    }
  }

  return (
    <div className="space-y-6" data-testid="staff-reports-page">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{t('reports.title')}</h1>
        <p className="text-gray-500 mt-2">{t('reports.subtitle')}</p>
      </div>

      {/* Date Range Filter */}
      <Card>
        <CardHeader>
          <CardTitle>{t('reports.period')}</CardTitle>
          <CardDescription>{t('export.description')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex items-end gap-4 flex-wrap">
            <div className="space-y-2">
              <Label htmlFor="date_from">{t('export.dateFrom')}</Label>
              <Input
                id="date_from"
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                data-testid="staff-reports-date-from"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="date_to">{t('export.dateTo')}</Label>
              <Input
                id="date_to"
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                data-testid="staff-reports-date-to"
              />
            </div>
            <Button
              variant="outline"
              onClick={() => {
                setDateFrom(format(subDays(new Date(), 30), 'yyyy-MM-dd'))
                setDateTo(format(new Date(), 'yyyy-MM-dd'))
              }}
            >
              {t('common:last30Days')}
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setDateFrom(format(subDays(new Date(), 7), 'yyyy-MM-dd'))
                setDateTo(format(new Date(), 'yyyy-MM-dd'))
              }}
            >
              {t('common:week')}
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* KPI Cards */}
      {isLoadingPayout || isLoadingAttendance ? (
        <div className="grid gap-4 md:grid-cols-4">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      ) : payoutData && attendanceData ? (
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.kpiCards.totalHours')}</CardTitle>
              <Clock className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{payoutData.summary.total_hours.toFixed(1)} h</div>
              <p className="text-xs text-muted-foreground">
                {payoutData.summary.individual_hours.toFixed(1)} 1:1 + {payoutData.summary.group_hours.toFixed(1)} {t('common:group')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.kpiCards.totalSessions')}</CardTitle>
              <CalendarIcon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{attendanceData.summary.total_sessions}</div>
              <p className="text-xs text-muted-foreground">
                {attendanceData.summary.attended} {t('common:attended')} · {attendanceData.summary.no_shows} {t('common:noShow')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.kpiCards.attendanceRate')}</CardTitle>
              <TrendingUp className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{attendanceData.summary.attendance_rate.toFixed(1)}%</div>
              <p className="text-xs text-muted-foreground">
                {attendanceData.summary.not_checked_in} {t('common:notCheckedIn')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('reports.kpiCards.totalEarnings')}</CardTitle>
              <DollarSign className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {payoutData.summary.total_earnings.toLocaleString()} {payoutData.summary.currency}
              </div>
              <p className="text-xs text-muted-foreground">
                {payoutData.summary.hourly_rate.toLocaleString()} {payoutData.summary.currency}/h
              </p>
            </CardContent>
          </Card>
        </div>
      ) : null}

      {/* Session Breakdown Table */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>{t('reports.sessionBreakdown.title')}</CardTitle>
              <CardDescription>
                {dateFrom} - {dateTo}
              </CardDescription>
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={handleExportAttendance}
                disabled={isExporting || isLoadingAttendance}
                data-testid="export-attendance-btn"
              >
                <FileDown className="h-4 w-4 mr-2" />
                {t('export.attendanceReport')}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleExportPayout}
                disabled={isExporting || isLoadingPayout}
                data-testid="export-payout-btn"
              >
                <FileDown className="h-4 w-4 mr-2" />
                {t('export.payoutReport')}
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isLoadingAttendance ? (
            <div className="space-y-2">
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
            </div>
          ) : attendanceData && attendanceData.sessions.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('reports.sessionBreakdown.date')}</TableHead>
                  <TableHead>{t('reports.sessionBreakdown.time')}</TableHead>
                  <TableHead>{t('reports.sessionBreakdown.client')}</TableHead>
                  <TableHead>{t('reports.sessionBreakdown.type')}</TableHead>
                  <TableHead className="text-right">{t('reports.sessionBreakdown.duration')}</TableHead>
                  <TableHead>{t('reports.sessionBreakdown.status')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {attendanceData.sessions.map((session, idx) => (
                  <TableRow key={`${session.date}-${idx}`}>
                    <TableCell className="font-medium">{session.date}</TableCell>
                    <TableCell>{session.time}</TableCell>
                    <TableCell>{session.client_name || session.class_name || '-'}</TableCell>
                    <TableCell>
                      <Badge variant={session.type === 'INDIVIDUAL' ? 'default' : 'secondary'}>
                        {session.type === 'INDIVIDUAL' ? '1:1' : t('common:group')}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">{session.room_name}</TableCell>
                    <TableCell>
                      {session.attendance_status === 'attended' && (
                        <Badge variant="default" className="bg-green-600">
                          {t('common:attended')}
                        </Badge>
                      )}
                      {session.attendance_status === 'no_show' && (
                        <Badge variant="destructive">{t('common:noShow')}</Badge>
                      )}
                      {!session.attendance_status && (
                        <Badge variant="outline">{t('common:notCheckedIn')}</Badge>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <p className="text-center text-gray-500 py-8">{t('reports.sessionBreakdown.noSessions')}</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
